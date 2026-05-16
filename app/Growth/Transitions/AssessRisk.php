<?php

namespace App\Growth\Transitions;

/**
 * Assess a risk: `identified` → `assessed`.
 */
class AssessRisk extends Transition
{
    public function allowedFrom(): array
    {
        return ['identified'];
    }

    public function targetStatus(): string
    {
        return 'assessed';
    }

    public function verb(): string
    {
        return 'assess';
    }

    public function subjectLabel(): string
    {
        return 'risk';
    }
}
