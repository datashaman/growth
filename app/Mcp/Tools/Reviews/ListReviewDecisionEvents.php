<?php

namespace App\Mcp\Tools\Reviews;

use App\Models\Review;
use App\Models\ReviewDecisionEvent;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('List append-only decision audit events for reviews in a project or for a specific review.')]
class ListReviewDecisionEvents extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'project_id' => 'required_without:review_id|nullable|string|owned_project',
            'review_id' => 'nullable|string|owned_review',
            'limit' => 'nullable|integer|min:1|max:200',
            'offset' => 'nullable|integer|min:0',
        ]);

        $limit = $data['limit'] ?? 50;
        $offset = $data['offset'] ?? 0;

        $query = ReviewDecisionEvent::query()
            ->with(['review:id,project_id,title,type', 'recordedBy:id,name,email']);

        if (isset($data['review_id'])) {
            $query->where('review_id', $data['review_id']);
        } else {
            $reviewIds = Review::query()
                ->where('project_id', $data['project_id'])
                ->pluck('id');
            $query->whereIn('review_id', $reviewIds);
        }

        $total = (clone $query)->count();

        $rows = $query
            ->orderByDesc('recorded_at')
            ->limit($limit)
            ->offset($offset)
            ->get();

        return Response::structured([
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset,
            'results' => $rows->map(fn (ReviewDecisionEvent $event): array => [
                'id' => $event->id,
                'review_id' => $event->review_id,
                'review' => $event->review?->title,
                'from_status' => $event->from_status,
                'to_status' => $event->to_status,
                'from_decision' => $event->from_decision,
                'to_decision' => $event->to_decision,
                'rationale' => $event->rationale,
                'recorded_by_user_id' => $event->recorded_by_user_id,
                'recorded_by' => $event->recordedBy?->name,
                'recorded_at' => $event->recorded_at?->toIso8601String(),
            ])->all(),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'project_id' => $schema->string()->description('Project ULID. Required unless review_id is provided.'),
            'review_id' => $schema->string()->description('Review ULID'),
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
