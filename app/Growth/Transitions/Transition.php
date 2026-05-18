<?php

namespace App\Growth\Transitions;

use App\Models\User;
use App\Notifications\WorkspaceNotification;
use App\Notifications\WorkspaceNotifier;
use App\Support\RoleContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Base verb-named status transition.
 *
 * A concrete transition validates that the subject's current status is an
 * accepted source state, applies the target status, and records an audit
 * row. The MCP tool and the webapp button are thin callers — transition
 * logic is never duplicated outside this class.
 *
 * Subjects must expose a `status` attribute. By default the audit row is
 * written to the polymorphic `status_transitions` table via a
 * `statusTransitions()` relation; governance entities override
 * {@see Transition::record()} to log elsewhere.
 */
abstract class Transition
{
    /**
     * Statuses the subject may legally be in for this transition to apply.
     *
     * @return list<string>
     */
    abstract public function allowedFrom(): array;

    /**
     * The status the subject moves to.
     */
    abstract public function targetStatus(): string;

    /**
     * Verb describing the transition, used in rejection messages ("start").
     */
    abstract public function verb(): string;

    /**
     * Human label for the subject, used in rejection messages ("work item").
     */
    abstract public function subjectLabel(): string;

    /**
     * Whether this transition requires a non-empty reason. Transitions that
     * capture a mandatory rationale (e.g. blocking a work item) override this;
     * the requirement is enforced here so no caller can bypass it.
     */
    public function requiresReason(): bool
    {
        return false;
    }

    /**
     * Apply the transition, recording an audit row.
     *
     * Returns the audit record created for the transition.
     *
     * @throws IllegalTransitionException when the subject's current status is not an accepted source state, or when a required reason is missing
     */
    public function apply(Model $subject, ?User $actor = null, ?string $reason = null): Model
    {
        if ($this->requiresReason() && ($reason === null || trim($reason) === '')) {
            throw new IllegalTransitionException("A reason is required to {$this->verb()} a {$this->subjectLabel()}.");
        }

        $applied = null;

        $record = DB::transaction(function () use ($subject, $actor, $reason, &$applied): Model {
            // Lock the subject row and re-read it under the lock, so two
            // concurrent transitions cannot both observe the same source state
            // and double-apply. Mutate the locked instance — not the caller's
            // possibly-stale copy — so a save never writes back stale columns.
            $locked = $subject->newQuery()
                ->lockForUpdate()
                ->findOrFail($subject->getKey());

            $from = $locked->getAttribute('status');

            if (! in_array($from, $this->allowedFrom(), true)) {
                throw new IllegalTransitionException($this->rejectionMessage($from));
            }

            $this->assertPreconditions($locked);

            $locked->setAttribute('status', $this->targetStatus());
            $this->decorateSubject($locked);
            $locked->save();

            // Keep the caller's instance in sync with the persisted row.
            $subject->setRawAttributes($locked->getAttributes(), true);
            $applied = $locked;

            return $this->record($locked, $from, $actor, $reason);
        });

        // Fan a catalogue notification out only once the transition has
        // committed, so a rolled-back transition notifies no one.
        $notification = $this->notification($applied);
        if ($notification !== null) {
            app(WorkspaceNotifier::class)->notifyWorkspace($applied, $notification, $actor);
        }

        return $record;
    }

    /**
     * The catalogue notification this transition emits once it has committed,
     * or null when the transition is not part of the notification catalogue.
     *
     * No-op by default; catalogue transitions override it. The notification
     * is fanned to every member of the subject's workspace minus the actor.
     */
    protected function notification(Model $subject): ?WorkspaceNotification
    {
        return null;
    }

    /**
     * Assert any non-status precondition for this transition, throwing
     * {@see IllegalTransitionException} when one is unmet.
     *
     * Runs inside the locked transaction, after the source-state check and
     * before the status is applied, so a failure rolls the transition back.
     * No-op by default; transitions guarded by a readiness gate override it.
     */
    protected function assertPreconditions(Model $subject): void {}

    /**
     * Apply any non-status attribute changes that are part of this transition,
     * within the same locked transaction. Overridden by transitions that set
     * decision fields, timestamps, or other attributes alongside the status.
     * No-op by default.
     */
    protected function decorateSubject(Model $subject): void {}

    /**
     * Record the audit row for this transition.
     *
     * The default writes a generic `status_transitions` row. Subjects with a
     * domain-specific event log (review decisions, change approvals) override
     * this to write to that log instead.
     */
    protected function record(Model $subject, string $from, ?User $actor, ?string $reason): Model
    {
        return $subject->statusTransitions()->create([
            'from_status' => $from,
            'to_status' => $this->targetStatus(),
            'reason' => $reason,
            'transitioned_by_user_id' => $actor?->getKey(),
            'acting_role' => app(RoleContext::class)->role()?->value,
            'transitioned_at' => now(),
        ]);
    }

    /**
     * Clear message explaining why the transition was rejected.
     */
    protected function rejectionMessage(?string $from): string
    {
        $current = $from === null ? 'unset' : str_replace('_', ' ', $from);
        $label = $this->subjectLabel();
        $article = in_array(strtolower($label[0] ?? ''), ['a', 'e', 'i', 'o', 'u'], true) ? 'an' : 'a';

        return "Cannot {$this->verb()} {$article} {$label} that is {$current}.";
    }
}
