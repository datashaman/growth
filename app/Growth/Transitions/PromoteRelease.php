<?php

namespace App\Growth\Transitions;

use App\Growth\Transitions\Concerns\EnforcesReleaseReadiness;
use App\Models\Release;
use Illuminate\Database\Eloquent\Model;

/**
 * Promote a release to release-candidate: `planned` → `candidate`.
 */
class PromoteRelease extends Transition
{
    use EnforcesReleaseReadiness;

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

    protected function assertPreconditions(Model $subject): void
    {
        /** @var Release $subject */
        $this->assertReleaseReadiness($subject);
    }
}
