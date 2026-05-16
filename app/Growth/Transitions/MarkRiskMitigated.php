<?php

namespace App\Growth\Transitions;

/**
 * Record a risk as mitigated: `mitigating` → `mitigated`.
 */
class MarkRiskMitigated extends Transition
{
    public function allowedFrom(): array
    {
        return ['mitigating'];
    }

    public function targetStatus(): string
    {
        return 'mitigated';
    }

    public function verb(): string
    {
        return 'mark as mitigated';
    }

    public function subjectLabel(): string
    {
        return 'risk';
    }
}
