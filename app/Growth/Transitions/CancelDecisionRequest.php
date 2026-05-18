<?php

namespace App\Growth\Transitions;

/**
 * Withdraw an open decision request before it is answered.
 */
class CancelDecisionRequest extends DecisionRequestTransition
{
    public function allowedFrom(): array
    {
        return ['open'];
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
