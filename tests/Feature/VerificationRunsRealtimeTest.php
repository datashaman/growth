<?php

use App\Events\ProjectDataChanged;
use App\Models\Project;
use App\Models\TestCase;
use App\Models\TestPlan;
use App\Models\TestRun;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    $this->project = Project::create([
        'workspace_id' => $this->user->active_workspace_id,
        'name' => 'Lunar Lander',
        'rigor_level' => 2,
    ]);

    $this->plan = TestPlan::create([
        'project_id' => $this->project->id,
        'level' => 'unit',
        'name' => 'Lander unit tests',
    ]);

    $this->case = TestCase::create([
        'test_plan_id' => $this->plan->id,
        'name' => 'engine ignition',
        'expected_results' => 'thrust within tolerance',
    ]);

    session(['selected_project_id' => $this->project->id]);
});

test('saving a TestRun dispatches ProjectDataChanged via case → plan → project', function () {
    Event::fake([ProjectDataChanged::class]);

    TestRun::create([
        'test_case_id' => $this->case->id,
        'status' => 'pass',
        'run_at' => now(),
    ]);

    Event::assertDispatched(ProjectDataChanged::class, fn (ProjectDataChanged $e) => $e->projectId === (string) $this->project->id);
});

test('verification page shows latest run for each case and refreshes on broadcast', function () {
    TestRun::create([
        'test_case_id' => $this->case->id,
        'status' => 'pass',
        'run_at' => now()->subMinute(),
    ]);

    $component = Livewire::test('pages::verification')
        ->assertSee('engine ignition')
        ->assertSee('pass');

    TestRun::create([
        'test_case_id' => $this->case->id,
        'status' => 'fail',
        'run_at' => now(),
    ]);

    $component
        ->call('onProjectDataChanged')
        ->assertSee('fail');
});

test('verification page renders no-runs placeholder for a never-run case when the column is shown', function () {
    // The Latest-run column is hidden when no case in the plan has run (#362),
    // so the "no runs" placeholder only appears alongside a case that has run.
    $ranCase = TestCase::create([
        'test_plan_id' => $this->plan->id,
        'name' => 'fuel valve check',
        'expected_results' => 'valve seals',
    ]);
    TestRun::create([
        'test_case_id' => $ranCase->id,
        'status' => 'pass',
        'run_at' => now(),
    ]);

    Livewire::test('pages::verification')
        ->assertSee('engine ignition')
        ->assertSee('no runs');
});
