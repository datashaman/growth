<?php

namespace App\Mcp\Tools\Plan;

use App\Models\Release;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('List release records for a project with work-item and deployment counts.')]
class ListReleases extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'project_id' => 'required|string|owned_project',
            'status' => 'nullable|in:'.implode(',', Release::STATUSES),
            'q' => 'nullable|string|max:255',
            'limit' => 'nullable|integer|min:1|max:200',
            'offset' => 'nullable|integer|min:0',
        ]);

        $limit = $data['limit'] ?? 50;
        $offset = $data['offset'] ?? 0;

        $query = Release::query()
            ->where('project_id', $data['project_id'])
            ->withCount(['workItems', 'deployments']);

        if (isset($data['status'])) {
            $query->where('status', $data['status']);
        }
        if (isset($data['q'])) {
            $query->where(fn ($q) => $q
                ->where('version', 'like', '%'.$data['q'].'%')
                ->orWhere('name', 'like', '%'.$data['q'].'%'));
        }

        $total = (clone $query)->count();

        $rows = $query->orderByDesc('released_at')->orderByDesc('created_at')
            ->limit($limit)
            ->offset($offset)
            ->get();

        return Response::structured([
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset,
            'results' => $rows->map(fn (Release $release): array => [
                'id' => $release->id,
                'version' => $release->version,
                'name' => $release->name,
                'status' => $release->status,
                'released_at' => $release->released_at?->toIso8601String(),
                'work_items' => $release->work_items_count,
                'deployments' => $release->deployments_count,
                'notes' => $release->notes,
            ])->all(),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'project_id' => $schema->string()->description('Project ULID')->required(),
            'status' => $schema->string()->description('Filter by release status')->enum(Release::STATUSES),
            'q' => $schema->string()->description('Substring match on version or name'),
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
