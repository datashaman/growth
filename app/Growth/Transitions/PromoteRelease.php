<?php

namespace App\Growth\Transitions;

/**
 * Promote a release to release-candidate: `planned` → `candidate`.
 */
class PromoteRelease extends Transition
{
    public function allowedFrom(): array
    {
        return ['planned'];
    }

    public function targetStatus(): string
    {
        return 'candidate';
    }

    public function verb(): string
    {
        return 'promote';
    }

    public function subjectLabel(): string
    {
        return 'release';
    }
}
