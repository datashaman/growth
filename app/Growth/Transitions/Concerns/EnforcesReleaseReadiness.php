<?php

namespace App\Growth\Transitions\Concerns;

use App\Growth\Assurance\ReleaseReadinessAssessor;
use App\Growth\Transitions\IllegalTransitionException;
use App\Models\Release;

trait EnforcesReleaseReadiness
{
    protected function assertReleaseReadiness(Release $release): void
    {
        $assessment = app(ReleaseReadinessAssessor::class)->assess($release->project()->firstOrFail(), $release);

        if ($assessment['status'] !== 'not_ready') {
            return;
        }

        throw new IllegalTransitionException(sprintf(
            'Cannot %s a %s until release readiness passes: %s.',
            $this->verb(),
            $this->subjectLabel(),
            implode(', ', $assessment['blockers']),
        ));
    }
}
