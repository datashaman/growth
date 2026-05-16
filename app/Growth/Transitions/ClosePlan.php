<?php

namespace App\Growth\Transitions;

/**
 * Close an active project plan: `active` → `closed`.
 */
class ClosePlan extends Transition
{
    public function allowedFrom(): array
    {
        return ['active'];
    }

    public function targetStatus(): string
    {
        return 'closed';
    }

    public function verb(): string
    {
        return 'close';
    }

    public function subjectLabel(): string
    {
        return 'plan';
    }
}
