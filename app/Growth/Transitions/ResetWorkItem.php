<?php

namespace App\Growth\Transitions;

/**
 * Reset a started work item back to the backlog: `in_progress` -> `todo`.
 */
class ResetWorkItem extends WorkItemTransition
{
    public function allowedFrom(): array
    {
        return ['in_progress'];
    }

    public function targetStatus(): string
    {
        return 'todo';
    }

    public function verb(): string
    {
        return 'reset';
    }
}
