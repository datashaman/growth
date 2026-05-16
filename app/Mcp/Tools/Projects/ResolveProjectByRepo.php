<?php

namespace App\Mcp\Tools\Projects;

use App\Models\Project;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Resolve a GitHub repository (owner/repo) to the Growth project bound to it.')]
class ResolveProjectByRepo extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'github_repo' => ['required', 'string', 'max:255', 'regex:/^[A-Za-z0-9_.-]+\/[A-Za-z0-9_.-]+$/'],
        ]);

        $project = Project::query()
            ->where('github_repo', $data['github_repo'])
            ->first(['id', 'name', 'status', 'github_repo']);

        if ($project === null) {
            return Response::structured([
                'found' => false,
                'github_repo' => $data['github_repo'],
                'project_id' => null,
                'name' => null,
                'status' => null,
            ]);
        }

        return Response::structured([
            'found' => true,
            'github_repo' => $project->github_repo,
            'project_id' => $project->id,
            'name' => $project->name,
            'status' => $project->status,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'github_repo' => $schema->string()->description('GitHub repository in owner/repo form')->required(),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'found' => $schema->boolean()->required(),
            'github_repo' => $schema->string()->required(),
            'project_id' => $schema->string(),
            'name' => $schema->string(),
            'status' => $schema->string(),
        ];
    }
}
