<?php

namespace App\Growth\Transitions;

/**
 * Cancel a work item: `todo`/`in_progress`/`blocked` → `cancelled`.
 */
class CancelWorkItem extends Transition
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

    public function subjectLabel(): string
    {
        return 'work item';
    }
}
