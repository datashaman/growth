<?php

namespace App\Growth\Transitions;

/**
 * Triage submitted feedback: `new` → `triaged`.
 */
class TriageFeedback extends Transition
{
    public function allowedFrom(): array
    {
        return ['new'];
    }

    public function targetStatus(): string
    {
        return 'triaged';
    }

    public function verb(): string
    {
        return 'triage';
    }

    public function subjectLabel(): string
    {
        return 'feedback';
    }
}
