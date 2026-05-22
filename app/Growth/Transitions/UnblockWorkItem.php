<?php

namespace App\Growth\Transitions;

/**
 * Unblock a work item: `blocked` → `in_progress`.
 */
class UnblockWorkItem extends WorkItemTransition
{
    public function allowedFrom(): array
    {
        return ['blocked'];
    }

    public function targetStatus(): string
    {
        return 'in_progress';
    }

    public function verb(): string
    {
        return 'unblock';
    }
}
