<?php

namespace App\Growth\Transitions;

/**
 * Mark a milestone as missed: `pending` → `missed`.
 */
class MissMilestone extends Transition
{
    public function allowedFrom(): array
    {
        return ['pending'];
    }

    public function targetStatus(): string
    {
        return 'missed';
    }

    public function verb(): string
    {
        return 'miss';
    }

    public function subjectLabel(): string
    {
        return 'milestone';
    }
}
