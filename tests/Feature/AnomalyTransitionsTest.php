<?php

use App\Growth\Transitions\IllegalTransitionException;
use App\Growth\Transitions\ReopenAnomaly;
use App\Growth\Transitions\StartAnomalyInvestigation;
use App\Models\Anomaly;
use App\Models\Project;
use App\Models\StatusTransition;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->project = Project::create([
        'workspace_id' => $this->user->active_workspace_id,
        'name' => 'Anomaly transitions',
        'rigor_level' => 2,
    ]);

    $this->makeAnomaly = fn (string $status): Anomaly => Anomaly::create([
        'project_id' => $this->project->id,
        'severity' => 'high',
        'status' => $status,
        'summary' => 'Checkout fails',
        'description' => 'The cart total is wrong.',
    ]);
});

// ---- transition actions ----

it('applies a legal anomaly transition and records an audit row', function () {
    $anomaly = ($this->makeAnomaly)('open');

    $transition = (new StartAnomalyInvestigation)->apply($anomaly, $this->user, 'Triaging');

    expect($anomaly->fresh()->status)->toBe('investigating')
        ->and($transition->from_status)->toBe('open')
        ->and($transition->to_status)->toBe('investigating')
        ->and($transition->reason)->toBe('Triaging')
        ->and($transition->transitioned_by_user_id)->toBe($this->user->id)
        ->and(StatusTransition::count())->toBe(1);
});

it('rejects an illegal anomaly source state without writing an audit row', function () {
    $anomaly = ($this->makeAnomaly)('open');

    expect(fn () => (new ReopenAnomaly)->apply($anomaly))
        ->toThrow(IllegalTransitionException::class, 'Cannot reopen an anomaly that is open.');

    expect($anomaly->fresh()->status)->toBe('open')
        ->and(StatusTransition::count())->toBe(0);
});

it('reopens an anomaly from either resolved or closed', function () {
    $resolved = ($this->makeAnomaly)('resolved');
    $closed = ($this->makeAnomaly)('closed');

    (new ReopenAnomaly)->apply($resolved);
    (new ReopenAnomaly)->apply($closed);

    expect($resolved->fresh()->status)->toBe('open')
        ->and($closed->fresh()->status)->toBe('open');
});

// ---- page access ----

it('404s the anomaly page for a user from another workspace', function () {
    $stranger = User::factory()->create();
    $strangerProject = Project::create([
        'workspace_id' => $stranger->active_workspace_id,
        'name' => 'Foreign',
        'rigor_level' => 1,
    ]);
    $foreignAnomaly = Anomaly::create([
        'project_id' => $strangerProject->id,
        'severity' => 'low',
        'status' => 'open',
        'summary' => 'Off limits',
        'description' => 'Body text.',
    ]);

    $this->actingAs($this->user)
        ->get(route('anomalies.show', $foreignAnomaly))
        ->assertNotFound();
});
