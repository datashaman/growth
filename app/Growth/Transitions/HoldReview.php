<?php

namespace App\Growth\Transitions;

/**
 * Record a review as held: `in_progress` → `held`.
 */
class HoldReview extends ReviewTransition
{
    public function allowedFrom(): array
    {
        return ['in_progress'];
    }

    public function targetStatus(): string
    {
        return 'held';
    }

    public function verb(): string
    {
        return 'hold';
    }
}
