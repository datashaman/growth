<?php

namespace App\Growth\Transitions;

/**
 * Record a deployment as successful: `in_progress` → `succeeded`.
 */
class MarkDeploymentSucceeded extends Transition
{
    public function allowedFrom(): array
    {
        return ['in_progress'];
    }

    public function targetStatus(): string
    {
        return 'succeeded';
    }

    public function verb(): string
    {
        return 'mark succeeded';
    }

    public function subjectLabel(): string
    {
        return 'deployment';
    }
}
