<?php

namespace App\Growth\Transitions;

/**
 * Withdraw a change request before it is implemented:
 * `proposed`, `under_review`, or `deferred` → `cancelled`.
 */
class CancelChangeRequest extends ChangeRequestTransition
{
    public function allowedFrom(): array
    {
        return ['proposed', 'under_review', 'deferred'];
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
