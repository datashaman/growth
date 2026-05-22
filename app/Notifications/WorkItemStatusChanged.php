<?php

namespace App\Notifications;

use App\Growth\Transitions\WorkItemTransition;
use App\Models\WorkItem;

/**
 * Catalogue event `work_item.status_changed`.
 *
 * Payload: the work item and its new status.
 * Recipients: the roles marked Informed (RACI "i") on the work item, minus
 * the actor — they asked to be kept in the loop on this item.
 * Emitted by every {@see WorkItemTransition}.
 */
class WorkItemStatusChanged extends WorkspaceNotification
{
    public function __construct(private readonly WorkItem $workItem) {}

    public function event(): string
    {
        return 'work_item.status_changed';
    }

    public function title(): string
    {
        return 'Work item status changed';
    }

    public function body(): string
    {
        return sprintf(
            '%s “%s” is now %s.',
            $this->workItem->reference(),
            $this->workItem->name,
            str_replace('_', ' ', (string) $this->workItem->status),
        );
    }

    public function url(): ?string
    {
        return route('work-items.show', ['workItem' => $this->workItem->id], false);
    }

    public function subject(): array
    {
        return ['work_item', $this->workItem->id];
    }
}
