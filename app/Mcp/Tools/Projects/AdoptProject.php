<?php

namespace App\Mcp\Tools\Projects;

use App\Growth\Plan\PlanBaseliner;
use App\Models\Project;
use App\Models\ProjectPlan;
use App\Support\WorkspaceContext;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Adopt an existing GitHub repository as a Growth project: bind the repo, stamp the adoption time, and set an `adoption`-kind plan baseline at HEAD. Idempotent — adopting an already-bound repo returns the existing project unchanged. Adopted projects start at rigor level 1; raise rigor as the backfill catches up.')]
class AdoptProject extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'github_repo' => ['required', 'string', 'max:255', 'regex:/^[A-Za-z0-9_.-]+\/[A-Za-z0-9_.-]+$/'],
            'name' => 'required|string|max:255',
        ]);

        // Idempotency: a repo already bound within this workspace returns its
        // project untouched — no duplicate, no re-stamp, no second baseline.
        $existing = Project::query()->where('github_repo', $data['github_repo'])->first();

        if ($existing !== null) {
            return $this->respond($existing, adopted: false);
        }

        // github_repo is globally unique; refuse a repo bound in another
        // workspace without leaking that workspace's project.
        $boundElsewhere = Project::query()
            ->withoutGlobalScope('workspace')
            ->where('github_repo', $data['github_repo'])
            ->exists();

        if ($boundElsewhere) {
            return new ResponseFactory(Response::error(
                'The repository '.$data['github_repo'].' is already bound to a project in another workspace.'
            ));
        }

        $project = DB::transaction(function () use ($data): Project {
            $project = Project::create([
                'name' => $data['name'],
                'github_repo' => $data['github_repo'],
                'rigor_level' => 1,
                'adopted_at' => now(),
                'workspace_id' => app(WorkspaceContext::class)->requireId(),
                'created_by_user_id' => auth()->id(),
            ]);

            $plan = ProjectPlan::create([
                'project_id' => $project->id,
                'status' => 'draft',
            ]);

            app(PlanBaseliner::class)->baseline($plan, auth()->user(), 'Adoption baseline at HEAD.', 'adoption');

            return $project;
        });

        return $this->respond($project, adopted: true);
    }

    /**
     * Shape the response for both the fresh-adoption and idempotent paths.
     */
    private function respond(Project $project, bool $adopted): ResponseFactory
    {
        $baseline = $project->projectPlan?->baselines()
            ->where('kind', 'adoption')
            ->orderBy('version')
            ->first();

        return Response::structured([
            'project_id' => $project->id,
            'name' => $project->name,
            'rigor_level' => $project->rigor_level,
            'status' => $project->status,
            'github_repo' => $project->github_repo,
            'adopted_at' => $project->adopted_at?->toIso8601String(),
            'adopted' => $adopted,
            'adoption_baseline_id' => $baseline?->id,
            'adoption_baseline_version' => $baseline?->version,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'github_repo' => $schema->string()
                ->description('The existing GitHub repository to adopt, in owner/repo form')
                ->required(),
            'name' => $schema->string()
                ->description('Name for the Growth project')
                ->required(),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'project_id' => $schema->string()->required(),
            'name' => $schema->string()->required(),
            'rigor_level' => $schema->integer()->required(),
            'status' => $schema->string()->required(),
            'github_repo' => $schema->string()->required(),
            'adopted_at' => $schema->string()->required(),
            'adopted' => $schema->boolean()->description('True when this call created the project; false when it already existed')->required(),
            'adoption_baseline_id' => $schema->string(),
            'adoption_baseline_version' => $schema->integer(),
        ];
    }
}
