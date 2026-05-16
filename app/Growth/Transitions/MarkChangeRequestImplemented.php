<?php

namespace App\Growth\Transitions;

/**
 * Record that an approved change request has been carried out:
 * `approved` → `implemented`.
 */
class MarkChangeRequestImplemented extends ChangeRequestTransition
{
    public function allowedFrom(): array
    {
        return ['approved'];
    }

    public function targetStatus(): string
    {
        return 'implemented';
    }

    public function verb(): string
    {
        return 'implement';
    }
}
