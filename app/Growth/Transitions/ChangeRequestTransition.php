<?php

namespace App\Growth\Transitions;

use App\Models\ChangeApprovalEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Base transition for change requests.
 *
 * Change requests keep their approval history in `change_approval_events`
 * rather than the generic `status_transitions` table, so this base records
 * there. Approval transitions also stamp the `decision` and `decided_at`
 * columns; lifecycle transitions (submit, implement, cancel) leave them alone.
 */
abstract class ChangeRequestTransition extends Transition
{
    /**
     * The change request's decision before this transition was applied.
     */
    protected ?string $fromDecision = null;

    public function subjectLabel(): string
    {
        return 'change request';
    }

    /**
     * The decision this transition records, or null for lifecycle-only moves.
     */
    protected function decision(): ?string
    {
        return null;
    }

    protected function decorateSubject(Model $subject): void
    {
        $this->fromDecision = $subject->getAttribute('decision');

        $decision = $this->decision();

        if ($decision !== null) {
            $subject->setAttribute('decision', $decision);
            $subject->setAttribute('decided_at', now());
        }
    }

    protected function record(Model $subject, string $from, ?User $actor, ?string $reason): Model
    {
        return ChangeApprovalEvent::create([
            'change_request_id' => $subject->getKey(),
            'recorded_by_user_id' => $actor?->getKey(),
            'from_status' => $from,
            'to_status' => $this->targetStatus(),
            'from_decision' => $this->fromDecision,
            'to_decision' => $subject->getAttribute('decision'),
            'rationale' => $reason,
            'recorded_at' => now(),
        ]);
    }
}
