<?php

namespace App\Mcp\Tools\Projects;

use App\Models\Project;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('List Growth projects with optional name search and pagination.')]
class ListProjects extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'q' => 'nullable|string|max:255',
            'status' => 'nullable|in:'.implode(',', Project::STATUSES),
            'limit' => 'nullable|integer|min:1|max:200',
            'offset' => 'nullable|integer|min:0',
        ]);

        $limit = $data['limit'] ?? 50;
        $offset = $data['offset'] ?? 0;
        $query = Project::query();

        if (isset($data['q'])) {
            $query->where('name', 'like', '%'.$data['q'].'%');
        }
        if (isset($data['status'])) {
            $query->where('status', $data['status']);
        }

        $total = (clone $query)->count();
        $rows = $query->orderBy('created_at')
            ->limit($limit)
            ->offset($offset)
            ->get(['id', 'name', 'description', 'rigor_level', 'status', 'created_at']);

        return Response::structured([
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset,
            'results' => $rows->map(fn ($project) => [
                'id' => $project->id,
                'name' => $project->name,
                'description' => $project->description,
                'rigor_level' => $project->rigor_level,
                'status' => $project->status,
                'created_at' => $project->created_at?->toIso8601String(),
            ])->all(),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'q' => $schema->string()->description('Substring match on project name'),
            'status' => $schema->string()->description('Filter by lifecycle status')->enum(Project::STATUSES),
            'limit' => $schema->integer()->description('Page size, default 50'),
            'offset' => $schema->integer()->description('Pagination offset, default 0'),
        ];
    }
}
