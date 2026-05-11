<?php

namespace App\Mcp\Tools\Plan;

use App\Models\Agent;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('List agents registered in a project. Filterable by kind and substring on name.')]
class ListAgents extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'project_id' => 'required|string|owned_project',
            'kind' => 'nullable|string|max:60',
            'q' => 'nullable|string|max:255',
            'limit' => 'nullable|integer|min:1|max:200',
            'offset' => 'nullable|integer|min:0',
        ]);

        $limit = $data['limit'] ?? 50;
        $offset = $data['offset'] ?? 0;

        $query = Agent::query()->where('project_id', $data['project_id']);
        if (isset($data['kind'])) {
            $query->where('kind', $data['kind']);
        }
        if (isset($data['q'])) {
            $query->where('name', 'like', '%'.$data['q'].'%');
        }

        $total = (clone $query)->count();

        $rows = $query
            ->withCount('roles')
            ->orderBy('name')
            ->limit($limit)
            ->offset($offset)
            ->get(['id', 'name', 'kind', 'description']);

        return Response::structured([
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset,
            'results' => $rows->map(fn ($a) => [
                'id' => $a->id,
                'name' => $a->name,
                'kind' => $a->kind,
                'description' => $a->description,
                'roles_count' => $a->roles_count,
            ])->all(),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'project_id' => $schema->string()->description('Project ULID')->required(),
            'kind' => $schema->string()->description('Filter by specialty tag'),
            'q' => $schema->string()->description('Substring match on name'),
            'limit' => $schema->integer()->description('Page size (1-200, default 50)'),
            'offset' => $schema->integer()->description('Offset (default 0)'),
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
