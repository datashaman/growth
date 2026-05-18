<?php

use App\Growth\Transitions\AcceptRisk;
use App\Growth\Transitions\AssessRisk;
use App\Growth\Transitions\IllegalTransitionException;
use App\Growth\Transitions\MarkRiskRealized;
use App\Models\Project;
use App\Models\Risk;
use App\Models\StatusTransition;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->project = Project::create([
        'workspace_id' => $this->user->active_workspace_id,
        'name' => 'Risk transitions',
        'rigor_level' => 2,
    ]);

    $this->makeRisk = fn (string $status): Risk => Risk::create([
        'project_id' => $this->project->id,
        'title' => 'Vendor lock-in',
        'category' => 'technical',
        'probability' => 'medium',
        'impact' => 'high',
        'status' => $status,
    ]);
});

// ---- transition actions ----

it('applies a legal risk transition and records an audit row', function () {
    $risk = ($this->makeRisk)('identified');

    $transition = (new AssessRisk)->apply($risk, $this->user, 'Quantified');

    expect($risk->fresh()->status)->toBe('assessed')
        ->and($transition->from_status)->toBe('identified')
        ->and($transition->to_status)->toBe('assessed')
        ->and($transition->reason)->toBe('Quantified')
        ->and($transition->transitioned_by_user_id)->toBe($this->user->id)
        ->and(StatusTransition::count())->toBe(1);
});

it('rejects an illegal risk source state without writing an audit row', function () {
    $risk = ($this->makeRisk)('closed');

    expect(fn () => (new MarkRiskRealized)->apply($risk))
        ->toThrow(IllegalTransitionException::class, 'Cannot mark as realized a risk that is closed.');

    expect($risk->fresh()->status)->toBe('closed')
        ->and(StatusTransition::count())->toBe(0);
});

it('accepts a risk from either identified or assessed', function () {
    $identified = ($this->makeRisk)('identified');
    $assessed = ($this->makeRisk)('assessed');

    (new AcceptRisk)->apply($identified);
    (new AcceptRisk)->apply($assessed);

    expect($identified->fresh()->status)->toBe('accepted')
        ->and($assessed->fresh()->status)->toBe('accepted');
});

// ---- page access ----

it('404s the risk page for a user from another workspace', function () {
    $stranger = User::factory()->create();
    $strangerProject = Project::create([
        'workspace_id' => $stranger->active_workspace_id,
        'name' => 'Foreign',
        'rigor_level' => 1,
    ]);
    $foreignRisk = Risk::create([
        'project_id' => $strangerProject->id,
        'title' => 'Off limits',
        'category' => 'technical',
        'probability' => 'low',
        'impact' => 'low',
        'status' => 'identified',
    ]);

    $this->actingAs($this->user)
        ->get(route('risks.show', $foreignRisk))
        ->assertNotFound();
});
