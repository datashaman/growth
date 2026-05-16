<?php

namespace App\Growth\Transitions;

/**
 * Mark a release candidate as released: `candidate` → `released`.
 */
class MarkReleaseReleased extends Transition
{
    public function allowedFrom(): array
    {
        return ['candidate'];
    }

    public function targetStatus(): string
    {
        return 'released';
    }

    public function verb(): string
    {
        return 'release';
    }

    public function subjectLabel(): string
    {
        return 'release';
    }
}
