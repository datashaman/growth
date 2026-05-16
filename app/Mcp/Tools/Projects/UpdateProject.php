<?php

namespace App\Mcp\Tools\Projects;

use App\Models\Project;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Patch a Growth project — update name, description, rigor level, and/or GitHub repo. A project in `archived` or `closed` status is read-only; restore it to `active` with the restore-project tool before sending content changes. Status is not set here: it moves only through the activate-project, archive-project, close-project, and restore-project transitions.')]
class UpdateProject extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'id' => 'required|string|owned_project',
            'name' => 'sometimes|string|max:255',
            'description' => 'sometimes|nullable|string',
            'rigor_level' => 'sometimes|integer|between:1,4',
            'status' => 'prohibited',
            'github_repo' => ['sometimes', 'nullable', 'string', 'max:255', 'regex:/^[A-Za-z0-9_.-]+\/[A-Za-z0-9_.-]+$/', Rule::unique('projects', 'github_repo')->ignore($request->get('id'))],
        ], [
            'status.prohibited' => 'Project status is not set here. Use the activate-project, archive-project, close-project, and restore-project tools to move status through validated transitions.',
        ]);

        $project = Project::findOrFail($data['id']);
        unset($data['id']);

        $contentFields = array_intersect_key($data, array_flip(['name', 'description', 'rigor_level']));

        if (! $project->isMutable() && $contentFields !== []) {
            return new ResponseFactory(Response::error(
                "Project [{$project->name}] is {$project->status} and cannot be edited. Restore it to `active` with the restore-project tool first, then resend the content changes."
            ));
        }

        $project->update($data);

        return Response::structured([
            'id' => $project->id,
            'name' => $project->name,
            'status' => $project->status,
            'rigor_level' => $project->rigor_level,
            'github_repo' => $project->github_repo,
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
            'rigor_level' => $schema->integer()
                ->description('Project rigor level (1–4). Higher levels activate stricter linter rules: L2 requires milestones + work items; L3 adds RACI roles, plan baseline, recorded reviews, and acceptance criteria on all requirements; L4 is the ceiling (no rules unique to it today). Full activation table at `growth://rigor-levels`.'),
            'github_repo' => $schema->string()
                ->description('GitHub repository bound to this project, in owner/repo form (pass null to clear). Lets the growth-sync action resolve deployment and release events to this project.'),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->required(),
            'name' => $schema->string()->required(),
            'status' => $schema->string()->required()->description('Current project status (read-only; changes only through transition tools).'),
            'rigor_level' => $schema->integer()->required(),
            'github_repo' => $schema->string(),
        ];
    }
}
