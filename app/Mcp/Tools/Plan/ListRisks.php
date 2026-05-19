<?php

namespace App\Mcp\Tools\Plan;

use App\Models\Risk;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('List risks for a project. Filterable by category, probability, impact, status, owner_role_id, and substring.')]
class ListRisks extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'project_id' => 'required|string|owned_project',
            'category' => 'nullable|in:'.implode(',', Risk::CATEGORIES),
            'probability' => 'nullable|in:'.implode(',', Risk::EXPOSURES),
            'impact' => 'nullable|in:'.implode(',', Risk::EXPOSURES),
            'status' => 'nullable|in:'.implode(',', Risk::STATUSES),
            'owner_role_id' => 'nullable|string|owned_role',
            'q' => 'nullable|string|max:255',
            'limit' => 'nullable|integer|min:1|max:200',
            'offset' => 'nullable|integer|min:0',
        ]);

        $limit = $data['limit'] ?? 50;
        $offset = $data['offset'] ?? 0;

        $query = Risk::query()
            ->where('project_id', $data['project_id'])
            ->with('ownerRole:id,name');

        foreach (['category', 'probability', 'impact', 'status', 'owner_role_id'] as $field) {
            if (isset($data[$field])) {
                $query->where($field, $data[$field]);
            }
        }
        if (isset($data['q'])) {
            $query->where('title', 'like', '%'.$data['q'].'%');
        }

        $total = (clone $query)->count();

        $rows = $query
            ->orderBy('status')
            ->orderBy('title')
            ->limit($limit)
            ->offset($offset)
            ->get();

        return Response::structured([
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset,
            'results' => $rows->map(fn ($risk) => [
                'id' => $risk->id,
                'title' => $risk->title,
                'category' => $risk->category,
                'probability' => $risk->probability,
                'impact' => $risk->impact,
                'status' => $risk->status,
                'owner_role_id' => $risk->owner_role_id,
                'owner_role' => $risk->ownerRole?->name,
                'mitigation_plan' => $risk->mitigation_plan,
            ])->all(),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'project_id' => $schema->string()->description('Project ULID')->required(),
            'category' => $schema->string()->description('Filter by category')->enum(Risk::CATEGORIES),
            'probability' => $schema->string()->description('Filter by probability')->enum(Risk::EXPOSURES),
            'impact' => $schema->string()->description('Filter by impact')->enum(Risk::EXPOSURES),
            'status' => $schema->string()->description('Filter by lifecycle status')->enum(Risk::STATUSES),
            'owner_role_id' => $schema->string()->description('Filter by owner role ULID'),
            'q' => $schema->string()->description('Substring match on title'),
            'limit' => $schema->integer()->description('Page size (1-200, default 50)'),
            'offset' => $schema->integer()->description('Offset for pagination (default 0)'),
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
