<?php

namespace App\Mcp\Tools\Plan;

use App\Models\Deployment;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('List deployment records for a project or release with linked delivery evidence counts.')]
class ListDeployments extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'project_id' => 'required_without:release_id|nullable|string|owned_project',
            'release_id' => 'nullable|string|owned_release',
            'environment' => 'nullable|string|max:120',
            'status' => 'nullable|in:'.implode(',', Deployment::STATUSES),
            'limit' => 'nullable|integer|min:1|max:200',
            'offset' => 'nullable|integer|min:0',
        ]);

        $limit = $data['limit'] ?? 50;
        $offset = $data['offset'] ?? 0;

        $query = Deployment::query()
            ->with('release:id,version,name')
            ->withCount('deliveryLinks');

        if (isset($data['release_id'])) {
            $query->where('release_id', $data['release_id']);
        } else {
            $query->where('project_id', $data['project_id']);
        }
        foreach (['environment', 'status'] as $field) {
            if (isset($data[$field])) {
                $query->where($field, $data[$field]);
            }
        }

        $total = (clone $query)->count();

        $rows = $query->orderByDesc('deployed_at')->orderByDesc('created_at')
            ->limit($limit)
            ->offset($offset)
            ->get();

        return Response::structured([
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset,
            'results' => $rows->map(fn (Deployment $deployment): array => [
                'id' => $deployment->id,
                'release_id' => $deployment->release_id,
                'release_version' => $deployment->release?->version,
                'environment' => $deployment->environment,
                'status' => $deployment->status,
                'deployed_at' => $deployment->deployed_at?->toIso8601String(),
                'url' => $deployment->url,
                'delivery_links' => $deployment->delivery_links_count,
                'notes' => $deployment->notes,
            ])->all(),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'project_id' => $schema->string()->description('Project ULID. Required unless release_id is provided.'),
            'release_id' => $schema->string()->description('Release ULID'),
            'environment' => $schema->string()->description('Filter by environment'),
            'status' => $schema->string()->description('Filter by deployment status')->enum(Deployment::STATUSES),
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
