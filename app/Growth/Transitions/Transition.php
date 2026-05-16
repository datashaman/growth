<?php

namespace App\Growth\Transitions;

use App\Models\StatusTransition;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Base verb-named status transition.
 *
 * A concrete transition validates that the subject's current status is an
 * accepted source state, applies the target status, and records a
 * `status_transitions` audit row. The MCP tool and the webapp button are
 * thin callers — transition logic is never duplicated outside this class.
 *
 * Subjects must expose a `status` attribute and a `statusTransitions()`
 * polymorphic relation.
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
     * Apply the transition, recording an audit row.
     *
     * @throws IllegalTransitionException when the subject's current status is not an accepted source state
     */
    public function apply(Model $subject, ?User $actor = null, ?string $reason = null): StatusTransition
    {
        return DB::transaction(function () use ($subject, $actor, $reason): StatusTransition {
            // Lock the subject row and re-read its status under the lock, so two
            // concurrent transitions cannot both observe the same source state
            // and double-apply.
            $from = $subject->newQuery()
                ->lockForUpdate()
                ->findOrFail($subject->getKey())
                ->getAttribute('status');

            if (! in_array($from, $this->allowedFrom(), true)) {
                throw new IllegalTransitionException($this->rejectionMessage($from));
            }

            $subject->setAttribute('status', $this->targetStatus());
            $this->decorateSubject($subject);
            $subject->save();

            return $subject->statusTransitions()->create([
                'from_status' => $from,
                'to_status' => $this->targetStatus(),
                'reason' => $reason,
                'transitioned_by_user_id' => $actor?->getKey(),
                'transitioned_at' => now(),
            ]);
        });
    }

    /**
     * Apply any non-status attribute changes that are part of this transition,
     * within the same locked transaction. Overridden by transitions that move
     * more than the status (e.g. deferring a milestone also sets a new target
     * date). No-op by default.
     */
    protected function decorateSubject(Model $subject): void {}

    /**
     * Clear message explaining why the transition was rejected.
     */
    protected function rejectionMessage(?string $from): string
    {
        $current = $from === null ? 'unset' : str_replace('_', ' ', $from);

        return "Cannot {$this->verb()} a {$this->subjectLabel()} that is {$current}.";
    }
}
