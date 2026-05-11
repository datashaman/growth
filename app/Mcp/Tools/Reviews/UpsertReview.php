<?php

namespace App\Mcp\Tools\Reviews;

use App\Growth\Artifacts\ArtifactRegistry;
use App\Mcp\Tools\Reviews\Concerns\ValidatesReviewArtifacts;
use App\Models\Review;
use App\Models\ReviewDecisionEvent;
use App\Models\ReviewPlan;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Create or update an review review record. Supports management reviews, technical reviews, inspections, walkthroughs, and audits, with optional reviewed artifact targets.')]
class UpsertReview extends Tool
{
    use ValidatesReviewArtifacts;

    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'id' => 'nullable|string|owned_review',
            'project_id' => 'required|string|owned_project',
            'review_plan_id' => 'nullable|string|owned_review_plan',
            'owner_role_id' => 'nullable|string|owned_role',
            'type' => 'required|in:'.implode(',', Review::TYPES),
            'title' => 'required|string|max:255',
            'objective' => 'nullable|string',
            'status' => 'nullable|in:'.implode(',', Review::STATUSES),
            'planned_at' => 'nullable|date',
            'held_at' => 'nullable|date',
            'entry_criteria' => 'nullable|array',
            'entry_criteria.*' => 'string',
            'exit_criteria' => 'nullable|array',
            'exit_criteria.*' => 'string',
            'decision' => 'nullable|in:'.implode(',', Review::DECISIONS),
            'decision_rationale' => 'nullable|string',
            'summary' => 'nullable|string',
            'targets' => 'nullable|array',
            'targets.*.type' => 'required_with:targets|string|in:'.implode(',', array_keys($this->reviewableTypes())),
            'targets.*.id' => 'required_with:targets|string',
            'targets.*.context' => 'nullable|string|max:255',
        ]);

        $targets = $data['targets'] ?? null;
        $decisionRationale = $data['decision_rationale'] ?? null;
        unset($data['targets']);
        unset($data['decision_rationale']);

        if (isset($data['review_plan_id'])) {
            $plan = ReviewPlan::findOrFail($data['review_plan_id']);
            if ($plan->project_id !== $data['project_id']) {
                throw ValidationException::withMessages([
                    'review_plan_id' => 'Review plans must belong to the same project as the review.',
                ]);
            }
            if ($plan->type !== $data['type']) {
                throw ValidationException::withMessages([
                    'review_plan_id' => 'Review plan type must match the review type.',
                ]);
            }

            $data['objective'] ??= $plan->objective;
            $data['entry_criteria'] ??= $plan->entry_criteria;
            $data['exit_criteria'] ??= $plan->exit_criteria;
        }

        $id = $data['id'] ?? null;
        unset($data['id']);

        $beforeStatus = null;
        $beforeDecision = null;
        if ($id) {
            $review = Review::findOrFail($id);
            $beforeStatus = $review->status;
            $beforeDecision = $review->decision;
            $review->update($data);
        } else {
            $review = Review::create($data);
        }

        $this->recordDecisionEvent($review, $beforeStatus, $beforeDecision, $decisionRationale);

        if ($targets !== null) {
            $this->syncTargets($request, $review, $targets);
        }

        return Response::structured([
            'id' => $review->id,
            'project_id' => $review->project_id,
            'review_plan_id' => $review->review_plan_id,
            'type' => $review->type,
            'title' => $review->title,
            'status' => $review->status,
            'decision' => $review->decision,
            'decision_events' => $review->decisionEvents()->count(),
            'targets' => $review->targets()->count(),
            'created' => $review->wasRecentlyCreated,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->description('Existing review ULID. Omit to create.'),
            'project_id' => $schema->string()->description('Project ULID')->required(),
            'review_plan_id' => $schema->string()->description('Optional reusable review plan ULID'),
            'owner_role_id' => $schema->string()->description('Role ULID accountable for the review'),
            'type' => $schema->string()->description('Review type')->enum(Review::TYPES)->required(),
            'title' => $schema->string()->description('Review title')->required(),
            'objective' => $schema->string()->description('Review objective/scope'),
            'status' => $schema->string()->description('Review lifecycle status')->enum(Review::STATUSES),
            'planned_at' => $schema->string()->description('Planned review date/time'),
            'held_at' => $schema->string()->description('Actual review date/time'),
            'entry_criteria' => $schema->array()->description('Entry criteria checklist'),
            'exit_criteria' => $schema->array()->description('Exit criteria checklist'),
            'decision' => $schema->string()->description('Review decision')->enum(Review::DECISIONS),
            'decision_rationale' => $schema->string()->description('Reason/evidence for a decision change; recorded in the decision audit trail'),
            'summary' => $schema->string()->description('Review summary/record'),
            'targets' => $schema->array()->description('Artifacts under review: {type,id,context}'),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->required(),
            'project_id' => $schema->string()->required(),
            'review_plan_id' => $schema->string(),
            'type' => $schema->string()->required(),
            'title' => $schema->string()->required(),
            'status' => $schema->string()->required(),
            'decision' => $schema->string(),
            'decision_events' => $schema->integer()->required(),
            'targets' => $schema->integer()->required(),
            'created' => $schema->boolean()->required(),
        ];
    }

    private function syncTargets(Request $request, Review $review, array $targets): void
    {
        $rows = [];

        foreach ($targets as $target) {
            $artifact = $this->validateReviewable($target['type'], $target['id']);
            if (ArtifactRegistry::projectId($artifact) !== $review->project_id) {
                throw ValidationException::withMessages([
                    'targets' => 'Review targets must belong to the same project as the review.',
                ]);
            }

            $rows[] = [
                'reviewable_type' => $target['type'],
                'reviewable_id' => $target['id'],
                'context' => $target['context'] ?? null,
            ];
        }

        $review->targets()->delete();
        foreach ($rows as $row) {
            $review->targets()->create($row);
        }
    }

    private function recordDecisionEvent(Review $review, ?string $beforeStatus, ?string $beforeDecision, ?string $rationale): void
    {
        if ($review->decision === null && $beforeDecision === null) {
            return;
        }

        if ($review->status === $beforeStatus && $review->decision === $beforeDecision && $rationale === null) {
            return;
        }

        ReviewDecisionEvent::create([
            'review_id' => $review->id,
            'recorded_by_user_id' => Auth::id(),
            'from_status' => $beforeStatus,
            'to_status' => $review->status,
            'from_decision' => $beforeDecision,
            'to_decision' => $review->decision,
            'rationale' => $rationale,
            'recorded_at' => now(),
        ]);
    }
}
