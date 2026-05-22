<?php

namespace App\Growth\Transitions;

use App\Models\Role;
use App\Models\User;
use App\Models\WorkItem;
use App\Notifications\WorkItemStatusChanged;
use App\Notifications\WorkspaceNotifier;
use Illuminate\Database\Eloquent\Model;

/**
 * Base for every work-item status transition.
 *
 * Every work-item transition extends this (not {@see Transition} directly) so
 * the Informed-role notification fan happens automatically — a new verb that
 * forgets to extend it would silently drop that contract.
 */
abstract class WorkItemTransition extends Transition
{
    public function subjectLabel(): string
    {
        return 'work item';
    }

    /**
     * A work-item status change reaches only the roles marked Informed (RACI
     * "i") on the item — they asked to be kept in the loop — minus the actor.
     * Responsible/Accountable already see blocked items in their queue, and
     * Consulted is advisory, so neither is notified here.
     */
    protected function dispatchNotification(Model $subject, ?User $actor): void
    {
        /** @var WorkItem $subject */
        $informed = $subject->raciRoles()
            ->wherePivot('raci', 'i')
            ->with('users')
            ->get()
            ->flatMap(fn (Role $role): iterable => $role->users)
            ->unique('id')
            ->reject(fn (User $user): bool => $actor !== null && $user->is($actor))
            ->values();

        if ($informed->isEmpty()) {
            return;
        }

        app(WorkspaceNotifier::class)->notifyUsers($informed, new WorkItemStatusChanged($subject));
    }
}
