<?php

use App\Growth\Transitions\ActivatePlan;
use App\Growth\Transitions\IllegalTransitionException;
use App\Models\Project;
use App\Models\ProjectPlan;
use App\Models\StatusTransition;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->project = Project::create([
        'workspace_id' => $this->user->active_workspace_id,
        'name' => 'Apollo',
        'rigor_level' => 2,
    ]);

    $this->makePlan = fn (string $status): ProjectPlan => ProjectPlan::create([
        'project_id' => $this->project->id,
        'status' => $status,
    ]);

    $this->actingAs($this->user);
});

// ---- base action ----

it('rejects an illegal plan transition without writing a row', function () {
    $plan = ($this->makePlan)('draft');

    expect(fn () => (new ActivatePlan)->apply($plan))
        ->toThrow(IllegalTransitionException::class, 'Cannot activate a plan that is draft.');

    expect(StatusTransition::count())->toBe(0);
});

// ---- plan page buttons ----

it('activates a baselined plan from the plan page', function () {
    $plan = ($this->makePlan)('baselined');

    Livewire::test('pages::plan')
        ->assertSee('Activate plan')
        ->call('activatePlan')
        ->assertDispatched('toast-show', dataset: ['variant' => 'success']);

    expect($plan->fresh()->status)->toBe('active');
    expect(StatusTransition::query()->sole()->to_status)->toBe('active');
});

it('closes an active plan from the plan page', function () {
    $plan = ($this->makePlan)('active');

    Livewire::test('pages::plan')
        ->assertSee('Close plan')
        ->call('closePlan')
        ->assertDispatched('toast-show', dataset: ['variant' => 'success']);

    expect($plan->fresh()->status)->toBe('closed');
});

it('warns the user when a plan page transition is illegal', function () {
    $plan = ($this->makePlan)('draft');

    Livewire::test('pages::plan')
        ->call('activatePlan')
        ->assertDispatched('toast-show', dataset: ['variant' => 'danger']);

    expect($plan->fresh()->status)->toBe('draft')
        ->and(StatusTransition::count())->toBe(0);
});

it('shows only the plan transitions legal for the current status', function () {
    ($this->makePlan)('baselined');

    Livewire::test('pages::plan')
        ->assertSee('Project plan')
        ->assertSee('Activate plan')
        ->assertDontSee('Close plan');
});
