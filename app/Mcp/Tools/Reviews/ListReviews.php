<?php

namespace App\Mcp\Tools\Reviews;

use App\Models\Review;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('List review records for a project. Filterable by review readiness review type, status, decision, owner role, and substring. For findings, participants, and decision events tied to a review, use `trace-query` with the review id.')]
class ListReviews extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'project_id' => 'required|string|owned_project',
            'type' => 'nullable|in:'.implode(',', Review::TYPES),
            'status' => 'nullable|in:'.implode(',', Review::STATUSES),
            'decision' => 'nullable|in:'.implode(',', Review::DECISIONS),
            'owner_role_id' => 'nullable|string|owned_role',
            'q' => 'nullable|string|max:255',
            'limit' => 'nullable|integer|min:1|max:200',
            'offset' => 'nullable|integer|min:0',
        ]);

        $limit = $data['limit'] ?? 50;
        $offset = $data['offset'] ?? 0;

        $query = Review::query()
            ->where('project_id', $data['project_id'])
            ->with('ownerRole:id,name')
            ->withCount(['targets', 'findings']);

        foreach (['type', 'status', 'decision', 'owner_role_id'] as $field) {
            if (isset($data[$field])) {
                $query->where($field, $data[$field]);
            }
        }
        if (isset($data['q'])) {
            $query->where('title', 'like', '%'.$data['q'].'%');
        }

        $total = (clone $query)->count();

        $rows = $query
            ->orderBy('planned_at')
            ->orderBy('title')
            ->limit($limit)
            ->offset($offset)
            ->get();

        return Response::structured([
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset,
            'results' => $rows->map(fn (Review $review) => [
                'id' => $review->id,
                'type' => $review->type,
                'title' => $review->title,
                'status' => $review->status,
                'decision' => $review->decision,
                'owner_role_id' => $review->owner_role_id,
                'owner_role' => $review->ownerRole?->name,
                'planned_at' => $review->planned_at?->toIso8601String(),
                'held_at' => $review->held_at?->toIso8601String(),
                'targets' => $review->targets_count,
                'findings' => $review->findings_count,
            ])->all(),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'project_id' => $schema->string()->description('Project ULID')->required(),
            'type' => $schema->string()->description('Filter by review type')->enum(Review::TYPES),
            'status' => $schema->string()->description('Filter by review status')->enum(Review::STATUSES),
            'decision' => $schema->string()->description('Filter by decision')->enum(Review::DECISIONS),
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
