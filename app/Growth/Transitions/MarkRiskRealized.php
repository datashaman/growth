<?php

namespace App\Growth\Transitions;

/**
 * Record that a risk has materialised: any active state → `realized`.
 *
 * Rejects `closed` and `realized` source states — a closed risk is final,
 * and an already-realized risk cannot be realized again.
 */
class MarkRiskRealized extends Transition
{
    public function allowedFrom(): array
    {
        return ['identified', 'assessed', 'mitigating', 'mitigated', 'accepted'];
    }

    public function targetStatus(): string
    {
        return 'realized';
    }

    public function verb(): string
    {
        return 'mark as realized';
    }

    public function subjectLabel(): string
    {
        return 'risk';
    }
}
