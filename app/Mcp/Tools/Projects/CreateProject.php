<?php

namespace App\Mcp\Tools\Projects;

use App\Models\Project;
use App\Support\WorkspaceContext;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[IsDestructive(false)]
#[Description('Create a new Growth project with a project rigor level from 1 to 4.')]
class CreateProject extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'rigor_level' => 'nullable|integer|between:1,4',
            'status' => 'nullable|in:draft,active',
            'github_repo' => ['nullable', 'string', 'max:255', 'regex:/^[A-Za-z0-9_.-]+\/[A-Za-z0-9_.-]+$/', Rule::unique('projects', 'github_repo')],
        ]);

        $project = Project::create($data + [
            'rigor_level' => 2,
            'workspace_id' => app(WorkspaceContext::class)->requireId(),
            'created_by_user_id' => auth()->id(),
        ]);

        return Response::structured([
            'id' => $project->id,
            'name' => $project->name,
            'rigor_level' => $project->rigor_level,
            'status' => $project->status,
            'github_repo' => $project->github_repo,
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
                ->description('Initial project lifecycle status — `draft` for in-progress setup or `active` for ongoing work (default `active`). After creation, status moves only through the activate-project, archive-project, close-project, and restore-project transitions.')
                ->enum(['draft', 'active']),
            'github_repo' => $schema->string()
                ->description('GitHub repository bound to this project, in owner/repo form. Lets the growth-sync action resolve deployment and release events to this project.'),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->required(),
            'name' => $schema->string()->required(),
            'rigor_level' => $schema->integer()->required(),
            'status' => $schema->string()->required(),
            'github_repo' => $schema->string(),
        ];
    }
}
