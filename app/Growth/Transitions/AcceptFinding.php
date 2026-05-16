<?php

namespace App\Growth\Transitions;

/**
 * Accept a review finding: `open`/`dispositioned` → `accepted`.
 */
class AcceptFinding extends Transition
{
    public function allowedFrom(): array
    {
        return ['open', 'dispositioned'];
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
        return 'review finding';
    }
}
