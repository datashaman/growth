<?php

namespace App\Growth\Transitions;

/**
 * Close a project: `active` → `closed`.
 */
class CloseProject extends Transition
{
    public function allowedFrom(): array
    {
        return ['active'];
    }

    public function targetStatus(): string
    {
        return 'closed';
    }

    public function verb(): string
    {
        return 'close';
    }

    public function subjectLabel(): string
    {
        return 'project';
    }
}
