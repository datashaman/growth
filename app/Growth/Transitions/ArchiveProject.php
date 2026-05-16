<?php

namespace App\Growth\Transitions;

/**
 * Archive a project: `active` → `archived`.
 */
class ArchiveProject extends Transition
{
    public function allowedFrom(): array
    {
        return ['active'];
    }

    public function targetStatus(): string
    {
        return 'archived';
    }

    public function verb(): string
    {
        return 'archive';
    }

    public function subjectLabel(): string
    {
        return 'project';
    }
}
