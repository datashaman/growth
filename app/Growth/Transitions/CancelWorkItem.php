<?php

namespace App\Growth\Transitions;

/**
 * Cancel a work item: `todo`/`in_progress`/`blocked` → `cancelled`.
 */
class CancelWorkItem extends WorkItemTransition
{
    public function allowedFrom(): array
    {
        return ['todo', 'in_progress', 'blocked'];
    }

    public function targetStatus(): string
    {
        return 'cancelled';
    }

    public function verb(): string
    {
        return 'cancel';
    }
}
