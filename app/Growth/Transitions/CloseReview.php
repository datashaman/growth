<?php

namespace App\Growth\Transitions;

/**
 * Close a held review: `held` → `closed`.
 */
class CloseReview extends ReviewTransition
{
    public function allowedFrom(): array
    {
        return ['held'];
    }

    public function targetStatus(): string
    {
        return 'closed';
    }

    public function verb(): string
    {
        return 'close';
    }
}
