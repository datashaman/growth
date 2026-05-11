<?php

namespace App\Mcp\Tools\Reviews;

use App\Models\ReviewParticipant;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('List participant role assignments for a review, including responsibility, attendance, and signoff state.')]
class ListReviewParticipants extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'review_id' => 'required|string|owned_review',
            'responsibility' => 'nullable|in:'.implode(',', ReviewParticipant::RESPONSIBILITIES),
            'attendance_status' => 'nullable|in:'.implode(',', ReviewParticipant::ATTENDANCE_STATUSES),
        ]);

        $query = ReviewParticipant::query()
            ->where('review_id', $data['review_id'])
            ->with('role:id,name');

        foreach (['responsibility', 'attendance_status'] as $field) {
            if (isset($data[$field])) {
                $query->where($field, $data[$field]);
            }
        }

        $rows = $query
            ->orderBy('responsibility')
            ->get();

        return Response::structured([
            'total' => $rows->count(),
            'results' => $rows->map(fn (ReviewParticipant $participant) => [
                'id' => $participant->id,
                'review_id' => $participant->review_id,
                'role_id' => $participant->role_id,
                'role' => $participant->role?->name,
                'responsibility' => $participant->responsibility,
                'attendance_status' => $participant->attendance_status,
                'signed_off_at' => $participant->signed_off_at?->toIso8601String(),
                'notes' => $participant->notes,
            ])->all(),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'review_id' => $schema->string()->description('Review ULID')->required(),
            'responsibility' => $schema->string()->description('Filter by responsibility')->enum(ReviewParticipant::RESPONSIBILITIES),
            'attendance_status' => $schema->string()->description('Filter by attendance status')->enum(ReviewParticipant::ATTENDANCE_STATUSES),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'total' => $schema->integer()->required(),
            'results' => $schema->array()->required(),
        ];
    }
}
