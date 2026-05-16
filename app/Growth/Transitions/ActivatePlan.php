<?php

namespace App\Growth\Transitions;

/**
 * Activate a baselined project plan: `baselined` → `active`.
 */
class ActivatePlan extends Transition
{
    public function allowedFrom(): array
    {
        return ['baselined'];
    }

    public function targetStatus(): string
    {
        return 'active';
    }

    public function verb(): string
    {
        return 'activate';
    }

    public function subjectLabel(): string
    {
        return 'plan';
    }
}
