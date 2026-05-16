<?php

namespace App\Growth\Transitions;

/**
 * Accept a risk: `identified`/`assessed` → `accepted`.
 */
class AcceptRisk extends Transition
{
    public function allowedFrom(): array
    {
        return ['identified', 'assessed'];
    }

    public function targetStatus(): string
    {
        return 'accepted';
    }

    public function verb(): string
    {
        return 'accept';
    }

    public function subjectLabel(): string
    {
        return 'risk';
    }
}
