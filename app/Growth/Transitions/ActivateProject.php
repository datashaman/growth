<?php

namespace App\Growth\Transitions;

/**
 * Activate a project: `draft` → `active`.
 */
class ActivateProject extends Transition
{
    public function allowedFrom(): array
    {
        return ['draft'];
    }

    public function targetStatus(): string
    {
        return 'active';
    }

    public function verb(): string
    {
        return 'activate';
    }

    public function subjectLabel(): string
    {
        return 'project';
    }
}
