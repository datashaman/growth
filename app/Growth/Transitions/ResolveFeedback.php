<?php

namespace App\Growth\Transitions;

/**
 * Resolve submitted feedback: `new`/`triaged` → `resolved`.
 */
class ResolveFeedback extends Transition
{
    public function allowedFrom(): array
    {
        return ['new', 'triaged'];
    }

    public function targetStatus(): string
    {
        return 'resolved';
    }

    public function verb(): string
    {
        return 'resolve';
    }

    public function subjectLabel(): string
    {
        return 'feedback';
    }
}
