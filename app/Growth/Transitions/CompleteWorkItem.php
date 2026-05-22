<?php

namespace App\Growth\Transitions;

/**
 * Finish a work item: `in_progress` → `done`.
 */
class CompleteWorkItem extends WorkItemTransition
{
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
}
