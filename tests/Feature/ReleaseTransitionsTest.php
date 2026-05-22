<?php

use App\Growth\Assurance\ReleaseReadinessAssessor;
use App\Growth\Transitions\CancelRelease;
use App\Growth\Transitions\IllegalTransitionException;
use App\Growth\Transitions\MarkReleaseReleased;
use App\Growth\Transitions\PromoteRelease;
use App\Models\Project;
use App\Models\Release;
use App\Models\StatusTransition;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->project = Project::create([
        'workspace_id' => $this->user->active_workspace_id,
        'name' => 'Transitions',
        'rigor_level' => 2,
    ]);

    $this->makeRelease = fn (string $status): Release => Release::create([
        'project_id' => $this->project->id,
        'version' => '1.0.0',
        'status' => $status,
    ]);

    $this->bindReleaseReadiness = function (string $status, array $blockers = []): void {
        app()->instance(ReleaseReadinessAssessor::class, new class($status, $blockers) extends ReleaseReadinessAssessor
        {
            public function __construct(private readonly string $status, private readonly array $blockers) {}

            public function assess(Project $project, ?Release $release = null): array
            {
                return [
                    'status' => $this->status,
                    'blockers' => $this->blockers,
                ];
            }
        });
    };
});

// ---- base transition action ----

it('applies a legal release transition and records an audit row', function () {
    $release = ($this->makeRelease)('planned');
    ($this->bindReleaseReadiness)('ready');

    $transition = (new PromoteRelease)->apply($release, $this->user, 'Cutting RC');

    expect($release->fresh()->status)->toBe('candidate')
        ->and($transition->from_status)->toBe('planned')
        ->and($transition->to_status)->toBe('candidate')
        ->and($transition->reason)->toBe('Cutting RC')
        ->and($transition->transitioned_by_user_id)->toBe($this->user->id)
        ->and(StatusTransition::count())->toBe(1);
});

it('blocks promoting a release when release readiness has blockers', function () {
    $release = ($this->makeRelease)('planned');
    ($this->bindReleaseReadiness)('not_ready', ['no_successful_deployment']);

    expect(fn () => (new PromoteRelease)->apply($release, $this->user, 'Cutting RC'))
        ->toThrow(IllegalTransitionException::class, 'Cannot promote a release until release readiness passes: no_successful_deployment.');

    expect($release->fresh()->status)->toBe('planned')
        ->and(StatusTransition::count())->toBe(0);
});

it('blocks marking a candidate released when high exposure risks are still open', function () {
    $release = ($this->makeRelease)('candidate');
    ($this->bindReleaseReadiness)('not_ready', ['high_exposure_risks_open']);

    expect(fn () => (new MarkReleaseReleased)->apply($release, $this->user, 'Ship'))
        ->toThrow(IllegalTransitionException::class, 'Cannot release a release until release readiness passes: high_exposure_risks_open.');

    expect($release->fresh()->status)->toBe('candidate')
        ->and(StatusTransition::count())->toBe(0);
});

it('allows releasing when readiness is caution but has no blockers', function () {
    $release = ($this->makeRelease)('candidate');
    ($this->bindReleaseReadiness)('caution');

    (new MarkReleaseReleased)->apply($release, $this->user, 'Warnings accepted');

    expect($release->fresh()->status)->toBe('released')
        ->and(StatusTransition::count())->toBe(1);
});

it('rejects an illegal source state without writing an audit row', function () {
    $release = ($this->makeRelease)('released');

    expect(fn () => (new CancelRelease)->apply($release))
        ->toThrow(IllegalTransitionException::class, 'Cannot cancel a release that is released.');

    expect($release->fresh()->status)->toBe('released')
        ->and(StatusTransition::count())->toBe(0);
});
