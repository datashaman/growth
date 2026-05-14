<?php

namespace App\Mcp\Tools\Projects;

use App\Models\Project;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Patch a Growth project — update name, description, and/or project rigor level.')]
class UpdateProject extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'id' => 'required|string|owned_project',
            'name' => 'sometimes|string|max:255',
            'description' => 'sometimes|nullable|string',
            'integrity_level' => 'sometimes|integer|between:1,4',
        ]);

        $project = Project::findOrFail($data['id']);
        unset($data['id']);

        $project->update($data);

        return Response::structured([
            'id' => $project->id,
            'name' => $project->name,
            'integrity_level' => $project->integrity_level,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()
                ->description('Project ULID')
                ->required(),
            'name' => $schema->string()
                ->description('New name'),
            'description' => $schema->string()
                ->description('New description (pass null to clear)'),
            'integrity_level' => $schema->integer()
                ->description('Project rigor level (1–4). Higher levels activate stricter linter rules: L2 requires milestones + work items; L3 adds RACI roles, plan baseline, recorded reviews, and acceptance criteria on all requirements; L4 is the ceiling (no rules unique to it today). Full activation table at `growth://rigor-levels`.'),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->required(),
            'name' => $schema->string()->required(),
            'integrity_level' => $schema->integer()->required(),
        ];
    }
}
