<?php

use App\Growth\Transitions\HitMilestone;
use App\Growth\Transitions\IllegalTransitionException;
use App\Models\Milestone;
use App\Models\Project;
use App\Models\StatusTransition;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->project = Project::create([
        'workspace_id' => $this->user->active_workspace_id,
        'name' => 'Milestones',
        'rigor_level' => 2,
    ]);

    $this->makeMilestone = fn (string $status): Milestone => Milestone::create([
        'project_id' => $this->project->id,
        'name' => 'Beta',
        'status' => $status,
    ]);

    $this->actingAs($this->user);
});

// ---- base action ----

it('rejects an illegal milestone transition without writing a row', function () {
    $milestone = ($this->makeMilestone)('hit');

    expect(fn () => (new HitMilestone)->apply($milestone))
        ->toThrow(IllegalTransitionException::class, 'Cannot hit a milestone that is hit.');

    expect(StatusTransition::count())->toBe(0);
});

// ---- plan page buttons ----

it('hits a milestone from the plan page', function () {
    $milestone = ($this->makeMilestone)('pending');

    Livewire::test('pages::plan')
        ->call('hitMilestone', $milestone->id)
        ->assertDispatched('toast-show', dataset: ['variant' => 'success']);

    expect($milestone->fresh()->status)->toBe('hit');
    expect(StatusTransition::query()->sole()->to_status)->toBe('hit');
});

it('misses a milestone from the plan page', function () {
    $milestone = ($this->makeMilestone)('pending');

    Livewire::test('pages::plan')
        ->call('missMilestone', $milestone->id)
        ->assertDispatched('toast-show', dataset: ['variant' => 'success']);

    expect($milestone->fresh()->status)->toBe('missed');
});

it('warns the user when a plan page milestone transition is illegal', function () {
    $milestone = ($this->makeMilestone)('hit');

    Livewire::test('pages::plan')
        ->call('hitMilestone', $milestone->id)
        ->assertDispatched('toast-show', dataset: ['variant' => 'danger']);

    expect($milestone->fresh()->status)->toBe('hit')
        ->and(StatusTransition::count())->toBe(0);
});

it('shows transition controls only for pending milestones', function () {
    $milestone = ($this->makeMilestone)('pending');

    Livewire::test('pages::plan')
        ->assertSeeHtml("hitMilestone('{$milestone->id}')");

    $milestone->update(['status' => 'hit']);

    Livewire::test('pages::plan')
        ->assertDontSeeHtml("hitMilestone('{$milestone->id}')");
});
