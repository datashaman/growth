<?php

namespace App\Growth\Transitions;

use App\Models\Role;
use App\Models\User;
use App\Models\WorkItem;
use App\Notifications\WorkItemStatusChanged;
use App\Notifications\WorkspaceNotifier;
use App\Support\RoleContext;
use App\Support\SurfaceContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Base for every work-item status transition.
 *
 * Every work-item transition extends this (not {@see Transition} directly) so
 * the Informed-role notification fan happens automatically — a new verb that
 * forgets to extend it would silently drop that contract.
 */
abstract class WorkItemTransition extends Transition
{
    private const ROLLUP_REASONS = [
        'blocked' => 'Child work item statuses rolled up to blocked.',
        'cancelled' => 'Child work item statuses rolled up to cancelled.',
        'done' => 'All child work items are done.',
        'in_progress' => 'Child work item statuses rolled up to in progress.',
        'todo' => 'Child work item statuses rolled up to todo.',
    ];

    public function apply(Model $subject, ?User $actor = null, ?string $reason = null): Model
    {
        $record = parent::apply($subject, $actor, $reason);

        if ($subject instanceof WorkItem) {
            $this->rollUpAncestors($subject, $actor);
        }

        return $record;
    }

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

    private function rollUpAncestors(WorkItem $workItem, ?User $actor): void
    {
        $parentId = $workItem->parent_id;

        while ($parentId !== null) {
            $result = DB::transaction(function () use ($parentId, $actor): ?array {
                $parent = WorkItem::query()
                    ->lockForUpdate()
                    ->find($parentId);

                if (! $parent instanceof WorkItem) {
                    return null;
                }

                $to = $this->deriveParentStatus($parent);
                if ($to === null || $parent->status === $to) {
                    return ['parent' => $parent, 'changed' => false];
                }

                $from = $parent->status;
                $parent->status = $to;
                $parent->save();

                $this->recordRollup($parent, $from, $to, $actor, self::ROLLUP_REASONS[$to]);

                return ['parent' => $parent, 'changed' => true];
            });

            if ($result === null) {
                return;
            }

            /** @var WorkItem $rolledParent */
            $rolledParent = $result['parent'];

            if ($result['changed']) {
                $this->dispatchNotification($rolledParent, $actor);
            }

            $parentId = $rolledParent->parent_id;
        }
    }

    private function deriveParentStatus(WorkItem $parent): ?string
    {
        $statuses = $parent->children()->pluck('status');

        if ($statuses->isEmpty()) {
            return null;
        }

        if ($statuses->contains('blocked')) {
            return 'blocked';
        }

        if ($statuses->contains('in_progress')) {
            return 'in_progress';
        }

        $notCancelled = $statuses->reject(fn (string $status): bool => $status === 'cancelled');

        if ($notCancelled->isEmpty()) {
            return 'cancelled';
        }

        if ($notCancelled->every(fn (string $status): bool => $status === 'done')) {
            return 'done';
        }

        if ($notCancelled->contains('done')) {
            return 'in_progress';
        }

        return 'todo';
    }

    private function recordRollup(WorkItem $parent, string $from, string $to, ?User $actor, string $reason): void
    {
        $actingRole = app(RoleContext::class)->role();

        $parent->statusTransitions()->create([
            'from_status' => $from,
            'to_status' => $to,
            'reason' => $reason,
            'transitioned_by_user_id' => $actor?->getKey(),
            'acting_surface' => app(SurfaceContext::class)->surface()?->value,
            'acting_role_id' => $actingRole?->getKey(),
            'acting_role_name' => $actingRole?->name,
            'transitioned_at' => now(),
        ]);
    }
}
