<?php

namespace App\Growth\Transitions;

/**
 * Reopen a closed review finding: `closed` → `open`.
 */
class ReopenFinding extends Transition
{
    public function allowedFrom(): array
    {
        return ['closed'];
    }

    public function targetStatus(): string
    {
        return 'open';
    }

    public function verb(): string
    {
        return 'reopen';
    }

    public function subjectLabel(): string
    {
        return 'review finding';
    }
}
