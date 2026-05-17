<?php

namespace App\Growth\Transitions;

/**
 * Mark a milestone as achieved: `pending` → `achieved`.
 */
class AchieveMilestone extends Transition
{
    public function allowedFrom(): array
    {
        return ['pending'];
    }

    public function targetStatus(): string
    {
        return 'achieved';
    }

    public function verb(): string
    {
        return 'achieve';
    }

    public function subjectLabel(): string
    {
        return 'milestone';
    }
}
