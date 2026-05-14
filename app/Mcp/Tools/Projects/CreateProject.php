<?php

namespace App\Mcp\Tools\Projects;

use App\Models\Project;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Create a new Growth project with a project rigor level from 1 to 4.')]
class CreateProject extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'rigor_level' => 'nullable|integer|between:1,4',
            'status' => 'nullable|in:'.implode(',', Project::STATUSES),
        ]);

        $project = Project::create($data + [
            'rigor_level' => 2,
            'user_id' => auth()->id(),
        ]);

        return Response::structured([
            'id' => $project->id,
            'name' => $project->name,
            'rigor_level' => $project->rigor_level,
            'status' => $project->status,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()
                ->description('Project name')
                ->required(),
            'description' => $schema->string()
                ->description('Optional project description'),
            'rigor_level' => $schema->integer()
                ->description('Project rigor level (1–4, default 2). Higher levels activate stricter linter rules: L2 requires milestones + work items; L3 adds RACI roles, plan baseline, recorded reviews, and acceptance criteria on all requirements; L4 is the ceiling (no rules unique to it today). Full activation table at `growth://rigor-levels`.'),
            'status' => $schema->string()
                ->description('Project lifecycle status. Defaults to `active`. `draft` for in-progress setup, `active` for ongoing work, `archived` and `closed` are read-only (only status can change after).')
                ->enum(Project::STATUSES),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->required(),
            'name' => $schema->string()->required(),
            'rigor_level' => $schema->integer()->required(),
            'status' => $schema->string()->required(),
        ];
    }
}
