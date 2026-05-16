<?php

namespace App\Growth\Transitions;

/**
 * Reopen a finished or cancelled work item: `done`/`cancelled` → `todo`.
 */
class ReopenWorkItem extends Transition
{
    public function allowedFrom(): array
    {
        return ['done', 'cancelled'];
    }

    public function targetStatus(): string
    {
        return 'todo';
    }

    public function verb(): string
    {
        return 'reopen';
    }

    public function subjectLabel(): string
    {
        return 'work item';
    }
}
