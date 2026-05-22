<?php

/*
 * #373: the read views surface traceability. Intent links each concern to the
 * design views that address it; requirement detail shows the verification cases
 * (with run status) that verify it. Both handle the no-link empty state.
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

test('a concern links to the design views that address it', function () {
    $concern = $this->project->concerns()->create(['text' => 'Telemetry must stay available.']);
    $view = $this->project->designViews()->create(['viewpoint' => 'logical', 'name' => 'Telemetry pipeline']);
    $concern->designViews()->attach($view);

    Livewire::test('pages::intent')
        ->assertSee('Addressed by')
        ->assertSee('Telemetry pipeline')
        ->assertSee('1 architecture view')
        ->assertSee(route('architecture'), false)
        ->assertDontSee(route('architecture', ['project' => $this->project->id]), false);
});

test('a concern with multiple design views has one stable architecture link', function () {
    $concern = $this->project->concerns()->create(['text' => 'Telemetry must stay available.']);
    $dependencyView = $this->project->designViews()->create(['viewpoint' => 'dependency', 'name' => 'Redis dependencies']);
    $logicalView = $this->project->designViews()->create(['viewpoint' => 'logical', 'name' => 'Telemetry pipeline']);
    $concern->designViews()->attach([$logicalView->id, $dependencyView->id]);

    Livewire::test('pages::intent')
        ->assertSee('2 architecture views')
        ->assertSee('Redis dependencies, Telemetry pipeline')
        ->assertSeeHtmlInOrder([
            'href="'.route('architecture').'"',
            'Redis dependencies, Telemetry pipeline',
        ])
        ->assertDontSee(route('architecture', ['project' => $this->project->id]), false);
});

test('a concern with no design views shows the not-yet-addressed state', function () {
    $this->project->concerns()->create(['text' => 'Unaddressed concern.']);

    Livewire::test('pages::intent')
        ->assertSee('Unaddressed concern.')
        ->assertSee('Not yet addressed');
});

test('the requirement detail page shows the verification case and its run status', function () {
    $requirement = $this->project->requirements()->create([
        'doc' => 'srs', 'type' => 'functional', 'text' => 'The system shall record telemetry.',
    ]);
    $plan = $this->project->testPlans()->create(['level' => 'system', 'name' => 'Acceptance']);
    $case = $plan->cases()->create(['name' => 'Telemetry capture case', 'expected_results' => 'Frames captured.']);
    $case->runs()->create(['status' => 'pass', 'run_at' => now()]);
    $requirement->testCases()->attach($case);

    $this->get('/requirements/'.$requirement->id)
        ->assertOk()
        ->assertSee('Verification')
        ->assertSee('Telemetry capture case')
        ->assertSee('pass')
        ->assertSee(route('verification', ['project' => $this->project->id]), false);
});

test('a requirement with no verification cases shows the not-yet-verified state', function () {
    $requirement = $this->project->requirements()->create([
        'doc' => 'srs', 'type' => 'functional', 'text' => 'The system shall record telemetry.',
    ]);

    $this->get('/requirements/'.$requirement->id)
        ->assertOk()
        ->assertSee('Not yet verified');
});
