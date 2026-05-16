<?php

namespace App\Growth\Transitions;

use Illuminate\Database\Eloquent\Model;

/**
 * Defer a milestone to a later date: `pending` → `deferred`.
 *
 * The transition also applies a new target date in the same locked
 * transaction as the status change.
 */
class DeferMilestone extends Transition
{
    /**
     * @param  string  $newTargetDate  New target date in YYYY-MM-DD format
     */
    public function __construct(private readonly string $newTargetDate) {}

    public function allowedFrom(): array
    {
        return ['pending'];
    }

    public function targetStatus(): string
    {
        return 'deferred';
    }

    public function verb(): string
    {
        return 'defer';
    }

    public function subjectLabel(): string
    {
        return 'milestone';
    }

    protected function decorateSubject(Model $subject): void
    {
        $subject->setAttribute('target_date', $this->newTargetDate);
    }
}
