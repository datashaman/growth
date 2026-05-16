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

#[Description('Create or update a review record. Supports management reviews, technical reviews, inspections, walkthroughs, and audits, with optional reviewed artifact targets. New reviews start as `planned`; status is not set here — it moves only through the start-review, hold-review, close-review, and cancel-review transitions. The response includes a missing_prerequisites list summarising which lint-reviews readiness checks the review will currently fail (targets, participants, entry/exit criteria, inspection roles, review-plan expected responsibilities) so you can address them before running lint-reviews.')]
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
            'status' => 'prohibited',
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
        ], [
            'status.prohibited' => 'Review status is not set here. Use the start-review, hold-review, close-review, and cancel-review tools to move status through validated transitions.',
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
            $review = Review::create($data + ['status' => 'planned']);
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
            'missing_prerequisites' => $this->missingPrerequisites($review->fresh(['reviewPlan', 'targets', 'participants'])),
        ]);
    }

    /**
     * @return list<string>
     */
    private function missingPrerequisites(Review $review): array
    {
        $missing = [];

        if ($review->targets->isEmpty()) {
            $missing[] = 'targets: add target artifact(s) via the targets field (lint-reviews rule review.targets.empty is an error).';
        }
        if ($review->participants->isEmpty()) {
            $missing[] = 'participants: add participants via upsert-review-participant (lint-reviews rule review.participants.empty).';
        }
        if (empty($review->entry_criteria)) {
            $missing[] = 'entry_criteria: set the entry checklist; required while status is planned or in_progress (lint-reviews rule review.entry_criteria.empty).';
        }
        if (empty($review->exit_criteria)) {
            $missing[] = 'exit_criteria: set the exit checklist; required before status held or closed (lint-reviews rule review.exit_criteria.empty).';
        }

        $presentResponsibilities = $review->participants->pluck('responsibility')->all();

        if ($review->type === 'inspection') {
            foreach (['moderator', 'reviewer', 'recorder'] as $role) {
                if (! in_array($role, $presentResponsibilities, true)) {
                    $missing[] = "inspection role: add a {$role} participant (lint-reviews rule review.inspection.role_missing).";
                }
            }
        }

        if ($review->reviewPlan && ! empty($review->reviewPlan->expected_responsibilities)) {
            foreach ($review->reviewPlan->expected_responsibilities as $role) {
                if (! in_array($role, $presentResponsibilities, true)) {
                    $missing[] = "plan expects {$role} participant (lint-reviews rule review.plan_role_missing).";
                }
            }
        }

        return $missing;
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
            'missing_prerequisites' => $schema->array()
                ->description('Lint-reviews readiness checks the review currently fails. Empty when the review is fully set up.')
                ->required(),
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
