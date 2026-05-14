<?php

namespace App\Mcp\Tools\Projects;

use App\Models\Project;
use App\Support\WorkspaceContext;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Create or update a Growth project. Omit id to create; provide id to update. A project in `archived` or `closed` status is read-only except for its own `status` — to edit name/description/rigor_level, first restore it to `active`.')]
class UpsertProject extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'id' => 'nullable|string|owned_project',
            'name' => 'required_without:id|string|max:255',
            'description' => 'nullable|string',
            'rigor_level' => 'nullable|integer|between:1,4',
            'status' => 'nullable|in:'.implode(',', Project::STATUSES),
        ]);

        $id = $data['id'] ?? null;
        unset($data['id']);

        if ($id) {
            $existing = Project::findOrFail($id);
            $contentFields = array_intersect_key($data, array_flip(['name', 'description', 'rigor_level']));

            if (! $existing->isMutable() && $contentFields !== []) {
                return new ResponseFactory(Response::error(
                    "Project [{$existing->name}] is {$existing->status} and cannot be edited. Set `status` to `draft` or `active` first, then resend the content changes."
                ));
            }

            $existing->update($data);
            $project = $existing;
        } else {
            $project = Project::create($data + [
                'rigor_level' => 2,
                'workspace_id' => app(WorkspaceContext::class)->requireId(),
                'created_by_user_id' => auth()->id(),
            ]);
        }

        return Response::structured([
            'id' => $project->id,
            'name' => $project->name,
            'rigor_level' => $project->rigor_level,
            'status' => $project->status,
            'created' => $project->wasRecentlyCreated,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->description('Existing project ULID. Omit to create new.'),
            'name' => $schema->string()->description('Project name. Required when creating.'),
            'description' => $schema->string()->description('Optional project description'),
            'rigor_level' => $schema->integer()->description('AI-delivery rigor level (1–4, default 2). Higher levels activate stricter linter rules: L2 requires milestones + work items; L3 adds RACI roles, plan baseline, recorded reviews, and acceptance criteria on all requirements; L4 is the ceiling (no rules unique to it today). Full activation table at `growth://rigor-levels`.'),
            'status' => $schema->string()->description('Project lifecycle status. New projects default to `active`. `draft` for in-progress setup, `active` for ongoing work, `archived` and `closed` are read-only (only `status` can change).')->enum(Project::STATUSES),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->required(),
            'name' => $schema->string()->required(),
            'rigor_level' => $schema->integer()->required(),
            'status' => $schema->string()->required(),
            'created' => $schema->boolean()->required(),
        ];
    }
}
