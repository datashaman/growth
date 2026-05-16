<?php

namespace App\Growth\Transitions;

/**
 * Cancel a deployment before it starts: `planned` → `cancelled`.
 */
class CancelDeployment extends Transition
{
    public function allowedFrom(): array
    {
        return ['planned'];
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
        return 'deployment';
    }
}
