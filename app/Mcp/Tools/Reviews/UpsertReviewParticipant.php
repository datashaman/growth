<?php

namespace App\Mcp\Tools\Reviews;

use App\Models\Review;
use App\Models\ReviewParticipant;
use App\Models\Role;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[IsDestructive(false)]
#[Description('Create or update a review participant role assignment. Use this to record review readiness review roles such as moderator, reviewer, recorder, auditor, and approver, including attendance and signoff.')]
class UpsertReviewParticipant extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'id' => 'nullable|string|owned_review_participant',
            'review_id' => 'required|string|owned_review',
            'role_id' => 'required|string|owned_role',
            'responsibility' => 'required|in:'.implode(',', ReviewParticipant::RESPONSIBILITIES),
            'attendance_status' => 'nullable|in:'.implode(',', ReviewParticipant::ATTENDANCE_STATUSES),
            'signed_off_at' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);

        $review = Review::findOrFail($data['review_id']);
        $role = Role::findOrFail($data['role_id']);
        if ($role->project_id !== $review->project_id) {
            throw ValidationException::withMessages([
                'role_id' => 'Review participant roles must belong to the same project as the review.',
            ]);
        }

        $id = $data['id'] ?? null;
        unset($data['id']);

        $participant = $id
            ? tap(ReviewParticipant::findOrFail($id))->update($data)
            : ReviewParticipant::updateOrCreate([
                'review_id' => $data['review_id'],
                'role_id' => $data['role_id'],
                'responsibility' => $data['responsibility'],
            ], $data);

        return Response::structured([
            'id' => $participant->id,
            'review_id' => $participant->review_id,
            'role_id' => $participant->role_id,
            'responsibility' => $participant->responsibility,
            'attendance_status' => $participant->attendance_status,
            'signed_off_at' => $participant->signed_off_at?->toIso8601String(),
            'created' => $participant->wasRecentlyCreated,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->description('Existing review participant ULID. Omit to create.'),
            'review_id' => $schema->string()->description('Review ULID')->required(),
            'role_id' => $schema->string()->description('Project role ULID participating in the review')->required(),
            'responsibility' => $schema->string()->description('Review responsibility')->enum(ReviewParticipant::RESPONSIBILITIES)->required(),
            'attendance_status' => $schema->string()->description('Attendance status')->enum(ReviewParticipant::ATTENDANCE_STATUSES),
            'signed_off_at' => $schema->string()->description('Timestamp when this participant signed off'),
            'notes' => $schema->string()->description('Participant notes'),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->required(),
            'review_id' => $schema->string()->required(),
            'role_id' => $schema->string()->required(),
            'responsibility' => $schema->string()->required(),
            'attendance_status' => $schema->string()->required(),
            'signed_off_at' => $schema->string(),
            'created' => $schema->boolean()->required(),
        ];
    }
}
