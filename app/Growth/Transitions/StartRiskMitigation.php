<?php

namespace App\Growth\Transitions;

/**
 * Begin mitigating a risk: `assessed` → `mitigating`.
 */
class StartRiskMitigation extends Transition
{
    public function allowedFrom(): array
    {
        return ['assessed'];
    }

    public function targetStatus(): string
    {
        return 'mitigating';
    }

    public function verb(): string
    {
        return 'start mitigation on';
    }

    public function subjectLabel(): string
    {
        return 'risk';
    }
}
