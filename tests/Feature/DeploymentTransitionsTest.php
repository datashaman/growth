<?php

use App\Growth\Transitions\IllegalTransitionException;
use App\Growth\Transitions\RollBackDeployment;
use App\Growth\Transitions\StartDeployment;
use App\Models\Deployment;
use App\Models\Project;
use App\Models\StatusTransition;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->project = Project::create([
        'workspace_id' => $this->user->active_workspace_id,
        'name' => 'Transitions',
        'rigor_level' => 2,
    ]);

    $this->makeDeployment = fn (string $status): Deployment => Deployment::create([
        'project_id' => $this->project->id,
        'environment' => 'production',
        'status' => $status,
    ]);
});

// ---- base transition action ----

it('applies a legal deployment transition and records an audit row', function () {
    $deployment = ($this->makeDeployment)('planned');

    $transition = (new StartDeployment)->apply($deployment, $this->user, 'Rolling out');

    expect($deployment->fresh()->status)->toBe('in_progress')
        ->and($transition->from_status)->toBe('planned')
        ->and($transition->to_status)->toBe('in_progress')
        ->and($transition->reason)->toBe('Rolling out')
        ->and($transition->transitioned_by_user_id)->toBe($this->user->id)
        ->and(StatusTransition::count())->toBe(1);
});

it('rejects an illegal source state without writing an audit row', function () {
    $deployment = ($this->makeDeployment)('planned');

    expect(fn () => (new RollBackDeployment)->apply($deployment))
        ->toThrow(IllegalTransitionException::class, 'Cannot roll back a deployment that is planned.');

    expect($deployment->fresh()->status)->toBe('planned')
        ->and(StatusTransition::count())->toBe(0);
});

// ---- webapp buttons ----

it('shows a start button for a planned deployment and starts it', function () {
    $deployment = ($this->makeDeployment)('planned');

    $this->actingAs($this->user);

    Livewire::test('pages::evidence')
        ->assertSee('Start')
        ->call('startDeployment', $deployment->id)
        ->assertHasNoErrors()
        ->assertDispatched('toast-show', dataset: ['variant' => 'success']);

    expect($deployment->fresh()->status)->toBe('in_progress')
        ->and(StatusTransition::query()->sole()->to_status)->toBe('in_progress');
});

it('shows succeed and fail buttons for an in_progress deployment and succeeds it', function () {
    $deployment = ($this->makeDeployment)('in_progress');

    $this->actingAs($this->user);

    Livewire::test('pages::evidence')
        ->assertSee('Mark succeeded')
        ->assertSee('Mark failed')
        ->call('markDeploymentSucceeded', $deployment->id)
        ->assertHasNoErrors()
        ->assertDispatched('toast-show', dataset: ['variant' => 'success']);

    expect($deployment->fresh()->status)->toBe('succeeded');
});

it('rejects an illegal deployment transition from the webapp and warns the user', function () {
    $deployment = ($this->makeDeployment)('planned');

    $this->actingAs($this->user);

    Livewire::test('pages::evidence')
        ->call('rollBackDeployment', $deployment->id)
        ->assertHasNoErrors()
        ->assertDispatched('toast-show', dataset: ['variant' => 'danger']);

    expect($deployment->fresh()->status)->toBe('planned')
        ->and(StatusTransition::count())->toBe(0);
});
