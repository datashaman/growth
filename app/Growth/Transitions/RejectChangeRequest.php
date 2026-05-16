<?php

namespace App\Growth\Transitions;

/**
 * Reject a change request under review: `under_review` → `rejected`.
 */
class RejectChangeRequest extends ChangeRequestTransition
{
    public function allowedFrom(): array
    {
        return ['under_review'];
    }

    public function targetStatus(): string
    {
        return 'rejected';
    }

    public function verb(): string
    {
        return 'reject';
    }

    protected function decision(): ?string
    {
        return 'rejected';
    }
}
