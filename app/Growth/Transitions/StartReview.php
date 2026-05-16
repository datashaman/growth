<?php

namespace App\Growth\Transitions;

/**
 * Open a review: `planned` → `in_progress`.
 */
class StartReview extends ReviewTransition
{
    public function allowedFrom(): array
    {
        return ['planned'];
    }

    public function targetStatus(): string
    {
        return 'in_progress';
    }

    public function verb(): string
    {
        return 'start';
    }
}
