<?php

namespace App\Mcp\Tools\Reviews;

use App\Growth\Transitions\CloseReview as CloseReviewTransition;
use App\Growth\Transitions\IllegalTransitionException;
use App\Models\Review;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Close a review: move it from held to closed. Rejects any other source status with a clear message. Records a review decision event with the acting user and timestamp.')]
class CloseReview extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'review_id' => 'required|string|owned_review',
            'rationale' => 'nullable|string|max:1000',
        ]);

        $review = Review::findOrFail($data['review_id']);

        try {
            $event = (new CloseReviewTransition)->apply($review, auth()->user(), $data['rationale'] ?? null);
        } catch (IllegalTransitionException $e) {
            return new ResponseFactory(Response::error($e->getMessage()));
        }

        return Response::structured([
            'review_id' => $review->id,
            'from_status' => $event->from_status,
            'to_status' => $event->to_status,
            'decision_event_id' => $event->id,
            'recorded_at' => $event->recorded_at->toIso8601String(),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'review_id' => $schema->string()->description('Review ULID')->required(),
            'rationale' => $schema->string()->description('Optional note recorded with the decision event'),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'review_id' => $schema->string()->required(),
            'from_status' => $schema->string()->required(),
            'to_status' => $schema->string()->required(),
            'decision_event_id' => $schema->string()->required(),
            'recorded_at' => $schema->string()->required(),
        ];
    }
}
