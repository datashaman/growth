<?php

namespace App\Growth\Transitions;

/**
 * Begin work on a work item: `todo` → `in_progress`.
 */
class StartWorkItem extends WorkItemTransition
{
    public function allowedFrom(): array
    {
        return ['todo'];
    }

    public function targetStatus(): string
    {
        return 'in_progress';
    }

    public function verb(): string
    {
        return 'start';
    }
}
