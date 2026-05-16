<?php

namespace App\Growth\Transitions;

/**
 * Record a deployment as failed: `in_progress` → `failed`.
 */
class MarkDeploymentFailed extends Transition
{
    public function allowedFrom(): array
    {
        return ['in_progress'];
    }

    public function targetStatus(): string
    {
        return 'failed';
    }

    public function verb(): string
    {
        return 'mark failed';
    }

    public function subjectLabel(): string
    {
        return 'deployment';
    }
}
