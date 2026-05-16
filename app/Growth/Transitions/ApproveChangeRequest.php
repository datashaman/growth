<?php

namespace App\Growth\Transitions;

/**
 * Approve a change request under review: `under_review` → `approved`.
 */
class ApproveChangeRequest extends ChangeRequestTransition
{
    public function allowedFrom(): array
    {
        return ['under_review'];
    }

    public function targetStatus(): string
    {
        return 'approved';
    }

    public function verb(): string
    {
        return 'approve';
    }

    protected function decision(): ?string
    {
        return 'approved';
    }
}
