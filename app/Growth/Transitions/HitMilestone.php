<?php

namespace App\Growth\Transitions;

/**
 * Mark a milestone as reached: `pending` → `hit`.
 */
class HitMilestone extends Transition
{
    public function allowedFrom(): array
    {
        return ['pending'];
    }

    public function targetStatus(): string
    {
        return 'hit';
    }

    public function verb(): string
    {
        return 'hit';
    }

    public function subjectLabel(): string
    {
        return 'milestone';
    }
}
