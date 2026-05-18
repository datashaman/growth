<?php

namespace App\Growth\Transitions;

/**
 * Expire an open decision request whose deadline has passed. Driven by the
 * scheduled `decision-requests:expire` command.
 */
class ExpireDecisionRequest extends DecisionRequestTransition
{
    public function allowedFrom(): array
    {
        return ['open'];
    }

    public function targetStatus(): string
    {
        return 'expired';
    }

    public function verb(): string
    {
        return 'expire';
    }
}
