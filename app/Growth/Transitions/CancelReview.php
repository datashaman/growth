<?php

namespace App\Growth\Transitions;

/**
 * Cancel a review before it is held: `planned`/`in_progress` → `cancelled`.
 */
class CancelReview extends ReviewTransition
{
    public function allowedFrom(): array
    {
        return ['planned', 'in_progress'];
    }

    public function targetStatus(): string
    {
        return 'cancelled';
    }

    public function verb(): string
    {
        return 'cancel';
    }
}
