<?php

namespace App\Growth\Transitions;

/**
 * Close an anomaly: `resolved` → `closed`.
 */
class CloseAnomaly extends Transition
{
    public function allowedFrom(): array
    {
        return ['resolved'];
    }

    public function targetStatus(): string
    {
        return 'closed';
    }

    public function verb(): string
    {
        return 'close';
    }

    public function subjectLabel(): string
    {
        return 'anomaly';
    }
}
