<?php

/*
 * #364: the requirements register list shows a stable identifier, conveys
 * verification coverage per row, and can be filtered by type and priority.
 */

use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Str;
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

test('the list shows each requirement reference as its identifier', function () {
    $requirement = $this->project->requirements()->create([
        'doc' => 'srs',
        'type' => 'functional',
        'text' => 'The system shall ignite the descent engine.',
    ]);

    Livewire::test('pages::requirements.index')
        ->assertSee('ID')
        ->assertSee($requirement->reference())
        ->assertSeeHtml('href="'.route('requirements.show', $requirement).'"')
        ->assertSee('SRS-001')
        ->assertDontSee($requirement->slug);
});

test('the list uses the id as the row link and summarizes long requirement prose', function () {
    $statement = 'The system shall '.str_repeat('coordinate descent telemetry with mission control before committing to the next burn window. ', 6);
    $rationale = 'Rationale keeps the landing timeline reviewable without forcing every operator to inspect downstream verification assets.';

    $requirement = $this->project->requirements()->create([
        'doc' => 'srs',
        'type' => 'design_constraint',
        'priority' => 'medium',
        'text' => $statement,
        'rationale' => $rationale,
    ]);

    Livewire::test('pages::requirements.index')
        ->assertSee($requirement->reference())
        ->assertSeeHtml('href="'.route('requirements.show', $requirement).'"')
        ->assertSee(Str::limit($statement, 180))
        ->assertDontSee($statement)
        ->assertSee('Rationale:')
        ->assertSee($rationale)
        ->assertSee('design constraint');
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

test('active filters can be cleared', function () {
    $this->project->requirements()->create([
        'doc' => 'srs', 'type' => 'functional', 'priority' => 'high', 'text' => 'High priority abort path.',
    ]);
    $this->project->requirements()->create([
        'doc' => 'srs', 'type' => 'process', 'priority' => 'low', 'text' => 'Low priority review cadence.',
    ]);

    Livewire::test('pages::requirements.index')
        ->set('typeFilter', 'process')
        ->set('priorityFilter', 'low')
        ->assertSet('typeFilter', 'process')
        ->assertSet('priorityFilter', 'low')
        ->assertSee('Clear filters')
        ->assertSee('Low priority review cadence.')
        ->assertDontSee('High priority abort path.')
        ->call('clearFilters')
        ->assertSet('typeFilter', 'all')
        ->assertSet('priorityFilter', 'all')
        ->assertDontSee('Clear filters')
        ->assertSee('Low priority review cadence.')
        ->assertSee('High priority abort path.');
});

test('filter controls render side by side on normal width screens', function () {
    $this->project->requirements()->create([
        'doc' => 'srs', 'type' => 'functional', 'text' => 'Functional ignition control.',
    ]);

    Livewire::test('pages::requirements.index')
        ->assertSee('data-test="requirements-type-filter"', false)
        ->assertSee('data-test="requirements-priority-filter"', false)
        ->assertSee('sm:grid-cols-2', false)
        ->assertSee('sm:w-48', false);
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
