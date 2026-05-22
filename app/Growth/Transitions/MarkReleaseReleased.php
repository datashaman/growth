<?php

namespace App\Growth\Transitions;

use App\Growth\Transitions\Concerns\EnforcesReleaseReadiness;
use App\Models\Release;
use Illuminate\Database\Eloquent\Model;

/**
 * Mark a release candidate as released: `candidate` → `released`.
 */
class MarkReleaseReleased extends Transition
{
    use EnforcesReleaseReadiness;

    public function allowedFrom(): array
    {
        return ['candidate'];
    }

    public function targetStatus(): string
    {
        return 'released';
    }

    public function verb(): string
    {
        return 'release';
    }

    public function subjectLabel(): string
    {
        return 'release';
    }

    protected function assertPreconditions(Model $subject): void
    {
        /** @var Release $subject */
        $this->assertReleaseReadiness($subject);
    }
}
