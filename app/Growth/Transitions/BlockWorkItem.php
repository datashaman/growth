<?php

namespace App\Growth\Transitions;

/**
 * Block a work item: `todo`/`in_progress` → `blocked`.
 */
class BlockWorkItem extends WorkItemTransition
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

    public function requiresReason(): bool
    {
        return true;
    }
}
