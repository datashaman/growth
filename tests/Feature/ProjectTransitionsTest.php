<?php

use App\Growth\Transitions\ActivateProject;
use App\Growth\Transitions\IllegalTransitionException;
use App\Models\Project;
use App\Models\StatusTransition;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();

    $this->makeProject = fn (string $status): Project => Project::create([
        'workspace_id' => $this->user->active_workspace_id,
        'name' => 'Apollo',
        'rigor_level' => 2,
        'status' => $status,
    ]);

    $this->actingAs($this->user);
});

// ---- base action ----

it('rejects an illegal project transition without writing a row', function () {
    $project = ($this->makeProject)('active');

    expect(fn () => (new ActivateProject)->apply($project))
        ->toThrow(IllegalTransitionException::class, 'Cannot activate a project that is active.');

    expect(StatusTransition::count())->toBe(0);
});

// ---- dashboard buttons ----

it('activates a draft project from the dashboard', function () {
    $project = ($this->makeProject)('draft');

    Livewire::test('pages::dashboard')
        ->assertSee('Activate')
        ->call('activateProject')
        ->assertDispatched('toast-show', dataset: ['variant' => 'success']);

    expect($project->fresh()->status)->toBe('active');
    expect(StatusTransition::query()->sole()->to_status)->toBe('active');
});

it('archives an active project from the dashboard', function () {
    $project = ($this->makeProject)('active');

    Livewire::test('pages::dashboard')
        ->assertSee('Archive')
        ->call('archiveProject')
        ->assertDispatched('toast-show', dataset: ['variant' => 'success']);

    expect($project->fresh()->status)->toBe('archived');
});

it('closes an active project from the dashboard', function () {
    $project = ($this->makeProject)('active');

    Livewire::test('pages::dashboard')
        ->call('closeProject')
        ->assertDispatched('toast-show', dataset: ['variant' => 'success']);

    expect($project->fresh()->status)->toBe('closed');
});

it('restores an archived project from the dashboard', function () {
    $project = ($this->makeProject)('archived');

    Livewire::test('pages::dashboard')
        ->assertSee('Restore')
        ->call('restoreProject')
        ->assertDispatched('toast-show', dataset: ['variant' => 'success']);

    expect($project->fresh()->status)->toBe('active');
});

it('warns the user when a dashboard project transition is illegal', function () {
    $project = ($this->makeProject)('active');

    Livewire::test('pages::dashboard')
        ->call('activateProject')
        ->assertDispatched('toast-show', dataset: ['variant' => 'danger']);

    expect($project->fresh()->status)->toBe('active')
        ->and(StatusTransition::count())->toBe(0);
});

it('shows only the transitions legal for the current project status', function () {
    ($this->makeProject)('active');

    Livewire::test('pages::dashboard')
        ->assertSee('Archive')
        ->assertSee('Close')
        ->assertDontSee('Activate')
        ->assertDontSee('Restore');
});
