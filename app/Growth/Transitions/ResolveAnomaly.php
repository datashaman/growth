<?php

namespace App\Growth\Transitions;

/**
 * Resolve an anomaly: `investigating` → `resolved`.
 */
class ResolveAnomaly extends Transition
{
    public function allowedFrom(): array
    {
        return ['investigating'];
    }

    public function targetStatus(): string
    {
        return 'resolved';
    }

    public function verb(): string
    {
        return 'resolve';
    }

    public function subjectLabel(): string
    {
        return 'anomaly';
    }
}
