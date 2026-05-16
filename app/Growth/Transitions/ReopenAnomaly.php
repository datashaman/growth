<?php

namespace App\Growth\Transitions;

/**
 * Reopen an anomaly: `resolved`/`closed` → `open`.
 */
class ReopenAnomaly extends Transition
{
    public function allowedFrom(): array
    {
        return ['resolved', 'closed'];
    }

    public function targetStatus(): string
    {
        return 'open';
    }

    public function verb(): string
    {
        return 'reopen';
    }

    public function subjectLabel(): string
    {
        return 'anomaly';
    }
}
