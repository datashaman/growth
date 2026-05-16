<?php

namespace App\Growth\Transitions;

/**
 * Resolve a review finding: `dispositioned` → `resolved`.
 */
class ResolveFinding extends Transition
{
    public function allowedFrom(): array
    {
        return ['dispositioned'];
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
        return 'review finding';
    }
}
