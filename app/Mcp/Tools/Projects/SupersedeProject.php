<?php

namespace App\Mcp\Tools\Projects;

use App\Models\Project;
use App\Support\RoleContext;
use App\Support\SurfaceContext;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[IsDestructive]
#[Description('Mark one project as superseded by another project and move the GitHub repository binding to the replacement project.')]
class SupersedeProject extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'old_project_id' => ['required', 'string', 'owned_project', 'different:new_project_id'],
            'new_project_id' => ['required', 'string', 'owned_project'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $result = DB::transaction(function () use ($data): ResponseFactory|array {
            $projects = Project::query()
                ->whereIn('id', [$data['old_project_id'], $data['new_project_id']])
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            /** @var Project $oldProject */
            $oldProject = $projects->get($data['old_project_id']);
            /** @var Project $newProject */
            $newProject = $projects->get($data['new_project_id']);

            if ($oldProject->status === 'superseded') {
                return new ResponseFactory(Response::error('Project is already superseded.'));
            }

            if ($newProject->status === 'superseded') {
                return new ResponseFactory(Response::error('Replacement project cannot itself be superseded.'));
            }

            if (! in_array($newProject->status, ['draft', 'active'], true)) {
                return new ResponseFactory(Response::error('Replacement project must be draft or active.'));
            }

            $repo = $oldProject->github_repo;

            if ($repo !== null && $newProject->github_repo !== null && $newProject->github_repo !== $repo) {
                return new ResponseFactory(Response::error('Replacement project already has a different GitHub repository binding.'));
            }

            $fromStatus = $oldProject->status;
            $now = now();

            $oldProject->forceFill([
                'github_repo' => null,
                'status' => 'superseded',
                'superseded_by_project_id' => $newProject->id,
                'superseded_by_user_id' => auth()->id(),
                'superseded_at' => $now,
                'supersession_reason' => $data['reason'] ?? null,
            ])->save();

            if ($repo !== null && $newProject->github_repo === null) {
                $newProject->forceFill(['github_repo' => $repo])->save();
            }

            $actingRole = app(RoleContext::class)->role();
            $transition = $oldProject->statusTransitions()->create([
                'from_status' => $fromStatus,
                'to_status' => 'superseded',
                'reason' => $data['reason'] ?? null,
                'transitioned_by_user_id' => auth()->id(),
                'acting_surface' => app(SurfaceContext::class)->surface()?->value,
                'acting_role_id' => $actingRole?->getKey(),
                'acting_role_name' => $actingRole?->name,
                'transitioned_at' => $now,
            ]);

            return [
                'old_project_id' => $oldProject->id,
                'new_project_id' => $newProject->id,
                'from_status' => $fromStatus,
                'to_status' => 'superseded',
                'transferred_github_repo' => $repo,
                'transition_id' => $transition->id,
                'superseded_at' => $now->toIso8601String(),
            ];
        });

        if ($result instanceof ResponseFactory) {
            return $result;
        }

        return Response::structured($result);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'old_project_id' => $schema->string()->description('Project ULID to supersede')->required(),
            'new_project_id' => $schema->string()->description('Replacement project ULID')->required(),
            'reason' => $schema->string()->description('Optional note recorded with the supersession audit trail'),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'old_project_id' => $schema->string()->required(),
            'new_project_id' => $schema->string()->required(),
            'from_status' => $schema->string()->required(),
            'to_status' => $schema->string()->required(),
            'transferred_github_repo' => $schema->string(),
            'transition_id' => $schema->string()->required(),
            'superseded_at' => $schema->string()->required(),
        ];
    }
}
