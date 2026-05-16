<?php

namespace App\Growth\Transitions;

/**
 * Block a work item: `todo`/`in_progress` → `blocked`.
 */
class BlockWorkItem extends Transition
{
    public function allowedFrom(): array
    {
        return ['todo', 'in_progress'];
    }

    public function targetStatus(): string
    {
        return 'blocked';
    }

    public function verb(): string
    {
        return 'block';
    }

    public function subjectLabel(): string
    {
        return 'work item';
    }
}
