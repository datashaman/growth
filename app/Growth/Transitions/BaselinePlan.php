<?php

namespace App\Growth\Transitions;

/**
 * Baseline a project plan: `draft` → `baselined`.
 *
 * The immutable baseline snapshot is created by the baseline-plan tool; this
 * transition owns only the validated status move and its audit row.
 */
class BaselinePlan extends Transition
{
    public function allowedFrom(): array
    {
        return ['draft'];
    }

    public function targetStatus(): string
    {
        return 'baselined';
    }

    public function verb(): string
    {
        return 'baseline';
    }

    public function subjectLabel(): string
    {
        return 'plan';
    }
}
