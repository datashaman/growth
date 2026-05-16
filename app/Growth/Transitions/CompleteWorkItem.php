<?php

namespace App\Growth\Transitions;

/**
 * Finish a work item: `in_progress` → `done`.
 */
class CompleteWorkItem extends Transition
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

    public function subjectLabel(): string
    {
        return 'work item';
    }
}
