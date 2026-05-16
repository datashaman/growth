<?php

namespace App\Growth\Transitions;

/**
 * Begin a deployment: `planned` → `in_progress`.
 */
class StartDeployment extends Transition
{
    public function allowedFrom(): array
    {
        return ['planned'];
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
        return 'deployment';
    }
}
