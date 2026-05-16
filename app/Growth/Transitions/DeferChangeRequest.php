<?php

namespace App\Growth\Transitions;

/**
 * Defer a decision on a change request under review: `under_review` → `deferred`.
 */
class DeferChangeRequest extends ChangeRequestTransition
{
    public function allowedFrom(): array
    {
        return ['under_review'];
    }

    public function targetStatus(): string
    {
        return 'deferred';
    }

    public function verb(): string
    {
        return 'defer';
    }

    protected function decision(): ?string
    {
        return 'deferred';
    }
}
