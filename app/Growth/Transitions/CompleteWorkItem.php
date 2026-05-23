<?php

namespace App\Growth\Transitions;

use App\Models\User;
use App\Models\WorkItem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Finish a work item: `in_progress` → `done`.
 */
class CompleteWorkItem extends WorkItemTransition
{
    private const ROLLUP_REASON = 'All child work items are done.';

    public function apply(Model $subject, ?User $actor = null, ?string $reason = null): Model
    {
        $record = parent::apply($subject, $actor, $reason);

        if ($subject instanceof WorkItem) {
            $this->rollUpDoneParents($subject, $actor);
        }

        return $record;
    }

    public function allowedFrom(): array
    {
        return ['in_progress'];
    }

    public function targetStatus(): string
    {
        return 'done';
    }

    public function verb(): string
    {
        return 'complete';
    }

    private function rollUpDoneParents(WorkItem $workItem, ?User $actor): void
    {
        $parentId = $workItem->parent_id;

        while ($parentId !== null) {
            $completedParent = DB::transaction(function () use ($parentId, $actor): ?WorkItem {
                $parent = WorkItem::query()
                    ->lockForUpdate()
                    ->find($parentId);

                if (! $parent instanceof WorkItem) {
                    return null;
                }

                $from = $parent->status;

                if (! in_array($from, ['todo', 'in_progress'], true)) {
                    return null;
                }

                if (! $parent->children()->exists()) {
                    return null;
                }

                if ($parent->children()->where('status', '!=', 'done')->exists()) {
                    return null;
                }

                $parent->status = 'done';
                $parent->save();

                $this->record($parent, $from, $actor, self::ROLLUP_REASON);

                return $parent;
            });

            if (! $completedParent instanceof WorkItem) {
                return;
            }

            $this->dispatchNotification($completedParent, $actor);

            $parentId = $completedParent->parent_id;
        }
    }
}
