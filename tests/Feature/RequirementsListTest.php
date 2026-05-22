<?php

/*
 * #364: the requirements register list shows a stable identifier, conveys
 * verification coverage per row, and can be filtered by type and priority.
 */

use App\Models\Project;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    $this->project = Project::create([
        'workspace_id' => $this->user->active_workspace_id,
        'name' => 'Lunar Lander',
        'rigor_level' => 3,
    ]);

    session(['selected_project_id' => $this->project->id]);
});

test('the list shows each requirement identifier', function () {
    $requirement = $this->project->requirements()->create([
        'doc' => 'srs',
        'type' => 'functional',
        'text' => 'The system shall ignite the descent engine.',
    ]);

    Livewire::test('pages::requirements.index')
        ->assertSee('ID')
        ->assertSee($requirement->slug);
});

test('the list can be filtered by type', function () {
    $this->project->requirements()->create([
        'doc' => 'srs', 'type' => 'functional', 'text' => 'Functional ignition control.',
    ]);
    $this->project->requirements()->create([
        'doc' => 'srs', 'type' => 'process', 'text' => 'Process review cadence.',
    ]);

    Livewire::test('pages::requirements.index')
        ->assertSee('Functional ignition control.')
        ->assertSee('Process review cadence.')
        ->set('typeFilter', 'process')
        ->assertSee('Process review cadence.')
        ->assertDontSee('Functional ignition control.');
});

test('the list can be filtered by priority', function () {
    $this->project->requirements()->create([
        'doc' => 'srs', 'type' => 'functional', 'priority' => 'high', 'text' => 'High priority abort path.',
    ]);
    $this->project->requirements()->create([
        'doc' => 'srs', 'type' => 'functional', 'priority' => 'low', 'text' => 'Low priority telemetry colour.',
    ]);

    Livewire::test('pages::requirements.index')
        ->set('priorityFilter', 'high')
        ->assertSee('High priority abort path.')
        ->assertDontSee('Low priority telemetry colour.');
});

test('an empty filter result explains that the filter, not the register, is empty', function () {
    $this->project->requirements()->create([
        'doc' => 'srs', 'type' => 'functional', 'text' => 'Only functional requirement.',
    ]);

    Livewire::test('pages::requirements.index')
        ->set('typeFilter', 'process')
        ->assertSee('No requirements match the current filter.')
        ->assertDontSee('No requirements captured.');
});

test('a requirement with a passing test case reads as verified', function () {
    $requirement = $this->project->requirements()->create([
        'doc' => 'srs', 'type' => 'functional', 'text' => 'Verified requirement.',
    ]);
    $plan = $this->project->testPlans()->create(['level' => 'system', 'name' => 'Acceptance']);
    $case = $plan->cases()->create(['name' => 'Ignition case', 'expected_results' => 'Engine ignites.']);
    $case->runs()->create(['status' => 'pass', 'run_at' => now()]);
    $requirement->testCases()->attach($case);

    Livewire::test('pages::requirements.index')
        ->assertSee('verified');
});

test('a requirement whose only test case has not passed reads as covered', function () {
    $requirement = $this->project->requirements()->create([
        'doc' => 'srs', 'type' => 'functional', 'text' => 'Covered requirement.',
    ]);
    $plan = $this->project->testPlans()->create(['level' => 'system', 'name' => 'Acceptance']);
    $case = $plan->cases()->create(['name' => 'Failing case', 'expected_results' => 'Engine ignites.']);
    $case->runs()->create(['status' => 'fail', 'run_at' => now()]);
    $requirement->testCases()->attach($case);

    Livewire::test('pages::requirements.index')
        ->assertSee('covered');
});

test('a requirement with no test cases reads as uncovered', function () {
    $this->project->requirements()->create([
        'doc' => 'srs', 'type' => 'functional', 'text' => 'Uncovered requirement.',
    ]);

    Livewire::test('pages::requirements.index')
        ->assertSee('uncovered');
});
