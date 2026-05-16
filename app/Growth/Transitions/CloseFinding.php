<?php

namespace App\Growth\Transitions;

/**
 * Close a review finding: `resolved`/`accepted` → `closed`.
 */
class CloseFinding extends Transition
{
    public function allowedFrom(): array
    {
        return ['resolved', 'accepted'];
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
        return 'review finding';
    }
}
