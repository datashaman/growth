<?php

namespace App\Growth\Transitions;

/**
 * Reopen submitted feedback: `triaged`/`resolved` → `new`.
 */
class ReopenFeedback extends FeedbackTransition
{
    public function allowedFrom(): array
    {
        return ['triaged', 'resolved'];
    }

    public function targetStatus(): string
    {
        return 'new';
    }

    public function verb(): string
    {
        return 'reopen';
    }
}
