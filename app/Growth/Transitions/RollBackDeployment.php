<?php

namespace App\Growth\Transitions;

/**
 * Roll back a finished deployment: `succeeded` or `failed` → `rolled_back`.
 */
class RollBackDeployment extends Transition
{
    public function allowedFrom(): array
    {
        return ['succeeded', 'failed'];
    }

    public function targetStatus(): string
    {
        return 'rolled_back';
    }

    public function verb(): string
    {
        return 'roll back';
    }

    public function subjectLabel(): string
    {
        return 'deployment';
    }
}
