<?php

namespace App\Growth\Transitions;

/**
 * Disposition a review finding: `open` → `dispositioned`.
 */
class DispositionFinding extends Transition
{
    public function allowedFrom(): array
    {
        return ['open'];
    }

    public function targetStatus(): string
    {
        return 'dispositioned';
    }

    public function verb(): string
    {
        return 'disposition';
    }

    public function subjectLabel(): string
    {
        return 'review finding';
    }
}
