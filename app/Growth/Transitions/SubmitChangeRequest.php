<?php

namespace App\Growth\Transitions;

/**
 * Submit a change request for review: `proposed` → `under_review`.
 */
class SubmitChangeRequest extends ChangeRequestTransition
{
    public function allowedFrom(): array
    {
        return ['proposed'];
    }

    public function targetStatus(): string
    {
        return 'under_review';
    }

    public function verb(): string
    {
        return 'submit';
    }
}
