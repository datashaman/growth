<?php

namespace App\Mcp\Tools;

use App\Models\Project;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('List Growth projects with optional name search and pagination.')]
class ListProjects extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'q' => 'nullable|string|max:255',
            'limit' => 'nullable|integer|min:1|max:200',
            'offset' => 'nullable|integer|min:0',
        ]);

        $limit = $data['limit'] ?? 50;
        $offset = $data['offset'] ?? 0;
        $query = Project::query();

        if (isset($data['q'])) {
            $query->where('name', 'like', '%'.$data['q'].'%');
        }

        $total = (clone $query)->count();
        $rows = $query->orderBy('created_at')
            ->limit($limit)
            ->offset($offset)
            ->get(['id', 'name', 'description', 'integrity_level', 'created_at']);

        return Response::structured([
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset,
            'results' => $rows->map(fn ($project) => [
                'id' => $project->id,
                'name' => $project->name,
                'description' => $project->description,
                'rigor_level' => $project->integrity_level,
                'created_at' => $project->created_at?->toIso8601String(),
            ])->all(),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'q' => $schema->string()->description('Substring match on project name'),
            'limit' => $schema->integer()->description('Page size, default 50'),
            'offset' => $schema->integer()->description('Pagination offset, default 0'),
        ];
    }
}
