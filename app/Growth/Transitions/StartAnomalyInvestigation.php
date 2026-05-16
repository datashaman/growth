<?php

namespace App\Growth\Transitions;

/**
 * Begin investigating an anomaly: `open` → `investigating`.
 */
class StartAnomalyInvestigation extends Transition
{
    public function allowedFrom(): array
    {
        return ['open'];
    }

    public function targetStatus(): string
    {
        return 'investigating';
    }

    public function verb(): string
    {
        return 'investigate';
    }

    public function subjectLabel(): string
    {
        return 'anomaly';
    }
}
