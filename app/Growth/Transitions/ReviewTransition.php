<?php

namespace App\Growth\Transitions;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Base transition for `Review`, a governance entity.
 *
 * A review's status history is recorded in the `review_decision_events`
 * table — the same audit trail used for decision changes — rather than the
 * polymorphic `status_transitions` table. The subject must expose a
 * `decisionEvents()` relation.
 */
abstract class ReviewTransition extends Transition
{
    public function subjectLabel(): string
    {
        return 'review';
    }

    protected function record(Model $subject, string $from, ?User $actor, ?string $reason): Model
    {
        return $subject->decisionEvents()->create([
            'recorded_by_user_id' => $actor?->getKey(),
            'from_status' => $from,
            'to_status' => $this->targetStatus(),
            'rationale' => $reason,
            'recorded_at' => now(),
        ]);
    }
}
