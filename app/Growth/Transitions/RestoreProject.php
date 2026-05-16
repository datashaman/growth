<?php

namespace App\Growth\Transitions;

/**
 * Restore an archived or closed project: `archived`/`closed` → `active`.
 */
class RestoreProject extends Transition
{
    public function allowedFrom(): array
    {
        return ['archived', 'closed'];
    }

    public function targetStatus(): string
    {
        return 'active';
    }

    public function verb(): string
    {
        return 'restore';
    }

    public function subjectLabel(): string
    {
        return 'project';
    }
}
