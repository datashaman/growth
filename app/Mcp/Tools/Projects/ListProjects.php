<?php

namespace App\Mcp\Tools\Projects;

use App\Models\Project;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('List Growth projects with optional name search and pagination. Returns total count alongside the page.')]
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
            ->get(['id', 'name', 'description', 'rigor_level', 'created_at']);

        return Response::structured([
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset,
            'results' => $rows->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'description' => $p->description,
                'rigor_level' => $p->rigor_level,
                'created_at' => $p->created_at?->toIso8601String(),
            ])->all(),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'q' => $schema->string()
                ->description('Substring match on project name'),
            'limit' => $schema->integer()
                ->description('Page size (1-200, default 50)'),
            'offset' => $schema->integer()
                ->description('Offset for pagination (default 0)'),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'total' => $schema->integer()->required(),
            'limit' => $schema->integer()->required(),
            'offset' => $schema->integer()->required(),
            'results' => $schema->array()->required(),
        ];
    }
}
