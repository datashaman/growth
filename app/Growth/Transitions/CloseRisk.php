<?php

namespace App\Growth\Transitions;

/**
 * Close a risk: `mitigated`/`accepted`/`realized` → `closed`.
 */
class CloseRisk extends Transition
{
    public function allowedFrom(): array
    {
        return ['mitigated', 'accepted', 'realized'];
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
        return 'risk';
    }
}
