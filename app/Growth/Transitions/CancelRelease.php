<?php

namespace App\Growth\Transitions;

/**
 * Cancel a release before it ships: `planned` or `candidate` → `cancelled`.
 */
class CancelRelease extends Transition
{
    public function allowedFrom(): array
    {
        return ['planned', 'candidate'];
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
        return 'release';
    }
}
