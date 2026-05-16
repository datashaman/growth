<?php

namespace App\Growth\Transitions;

/**
 * Begin work on a work item: `todo` → `in_progress`.
 */
class StartWorkItem extends Transition
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

    public function subjectLabel(): string
    {
        return 'work item';
    }
}
