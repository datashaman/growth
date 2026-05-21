<?php

namespace App\Growth\Transitions;

use App\Models\ChangeApprovalEvent;
use App\Models\ChangeRequest;
use App\Models\User;
use App\Notifications\ChangeRequestDecided;
use App\Notifications\ChangeRequestStatusChanged;
use App\Notifications\WorkspaceNotification;
use App\Notifications\WorkspaceNotifier;
use App\Support\WorkspaceContext;
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

    /**
     * The change request's status before this transition was applied,
     * captured so subscribers can be told what it moved between.
     */
    protected ?string $fromStatus = null;

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

    protected function decorateSubject(Model $subject, ?string $reason): void
    {
        $this->fromDecision = $subject->getAttribute('decision');

        $decision = $this->decision();

        if ($decision !== null) {
            $subject->setAttribute('decision', $decision);
            $subject->setAttribute('decided_at', now());

            // The reason supplied to a decision transition is the decision
            // rationale: stamp it onto the change request itself (not just the
            // approval event) so the `change.decision_rationale.empty` gate is
            // satisfied. A blank reason leaves any existing rationale intact.
            if ($reason !== null && trim($reason) !== '') {
                $subject->setAttribute('decision_rationale', $reason);
            }
        }
    }

    /**
     * Decision transitions (approve / reject / defer) are catalogue events;
     * lifecycle-only moves (submit, implement, cancel) are not.
     */
    protected function notification(Model $subject): ?WorkspaceNotification
    {
        if ($this->decision() === null) {
            return null;
        }

        /** @var ChangeRequest $subject */
        return new ChangeRequestDecided($subject);
    }

    /**
     * Notify the workspace catalogue (decision transitions only) and every
     * user subscribed to this change request, whatever the transition.
     */
    protected function dispatchNotification(Model $subject, ?User $actor): void
    {
        parent::dispatchNotification($subject, $actor);

        /** @var ChangeRequest $subject */
        $this->notifySubscribers($subject, $actor);
    }

    /**
     * Tell every subscriber the change request moved status, skipping the
     * actor who made the change. No-op when the change request has no
     * subscribers.
     *
     * This is independent of the workspace-wide {@see ChangeRequestDecided}
     * announcement: a subscriber who is also a workspace member receives both
     * on a decision transition — one for opting in, one for the CCB outcome.
     *
     * Each notification inherits the actor's {@see WorkspaceContext}
     * for its `workspace_id` stamp (via {@see WorkspaceNotifier::notifyUser}),
     * which matches the change request's workspace on every MCP-driven call.
     */
    private function notifySubscribers(ChangeRequest $subject, ?User $actor): void
    {
        $notifier = app(WorkspaceNotifier::class);

        $subject->subscriptions()
            ->with('user')
            ->get()
            ->pluck('user')
            ->filter()
            ->reject(fn (User $user): bool => $actor !== null && $user->is($actor))
            ->each(fn (User $user) => $notifier->notifyUser(
                $user,
                new ChangeRequestStatusChanged($subject, (string) $this->fromStatus, $this->targetStatus()),
            ));
    }

    protected function record(Model $subject, string $from, ?User $actor, ?string $reason): Model
    {
        $this->fromStatus = $from;

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
