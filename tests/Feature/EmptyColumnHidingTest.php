<?php

/*
 * #362: a table column that is empty for every row in view consumes width
 * while conveying nothing. Each affected surface hides such a column (header
 * and cells) when no row carries a value, and renders it otherwise.
 */

use App\Models\Project;
use App\Models\ToolInvocation;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    $this->project = Project::create([
        'workspace_id' => $this->user->active_workspace_id,
        'name' => 'Lunar Lander',
        'rigor_level' => 2,
    ]);

    session(['selected_project_id' => $this->project->id]);
});

test('intent hides the Viewpoint hints column when no concern has hints', function () {
    $this->project->concerns()->create(['text' => 'Telemetry must stay available.']);

    Livewire::test('pages::intent')
        ->assertSee('Telemetry must stay available.')
        ->assertDontSee('Viewpoint hints');
});

test('intent shows the Viewpoint hints column when a concern has hints', function () {
    $this->project->concerns()->create([
        'text' => 'Telemetry must stay available.',
        'viewpoint_hints' => ['operational'],
    ]);

    Livewire::test('pages::intent')
        ->assertSee('Viewpoint hints')
        ->assertSee('operational');
});

test('architecture hides Type and Purpose when no element populates them', function () {
    $view = $this->project->designViews()->create([
        'viewpoint' => 'logical',
        'name' => 'Telemetry pipeline',
    ]);
    $view->elements()->create(['kind' => 'entity', 'name' => 'Ingest gateway']);

    Livewire::test('pages::architecture')
        ->assertSee('Ingest gateway')
        ->assertDontSee('Type')
        ->assertDontSee('Purpose');
});

test('architecture shows Type and Purpose when an element populates them', function () {
    $view = $this->project->designViews()->create([
        'viewpoint' => 'logical',
        'name' => 'Telemetry pipeline',
    ]);
    $view->elements()->create([
        'kind' => 'entity',
        'name' => 'Ingest gateway',
        'type' => 'service',
        'purpose' => 'Accept telemetry packets.',
    ]);

    Livewire::test('pages::architecture')
        ->assertSee('Type')
        ->assertSee('service')
        ->assertSee('Purpose')
        ->assertSee('Accept telemetry packets.');
});

test('verification hides Environment and Latest run when no case populates them', function () {
    $plan = $this->project->testPlans()->create(['level' => 'system', 'name' => 'Acceptance']);
    $plan->cases()->create([
        'name' => 'Ingest within deadline',
        'objective' => 'Bounded latency.',
        'expected_results' => 'Packet acknowledged within deadline.',
    ]);

    Livewire::test('pages::verification')
        ->assertSee('Ingest within deadline')
        ->assertDontSee('Environment')
        ->assertDontSee('Latest run');
});

test('verification shows Environment and Latest run when a case populates them', function () {
    $plan = $this->project->testPlans()->create(['level' => 'system', 'name' => 'Acceptance']);
    $case = $plan->cases()->create([
        'name' => 'Ingest within deadline',
        'objective' => 'Bounded latency.',
        'expected_results' => 'Packet acknowledged within deadline.',
        'environment' => 'staging',
    ]);
    $case->runs()->create(['status' => 'pass', 'run_at' => now()]);

    Livewire::test('pages::verification')
        ->assertSee('Environment')
        ->assertSee('staging')
        ->assertSee('Latest run');
});

test('tool invocations hides Surface and Role when none are bound', function () {
    ToolInvocation::create([
        'workspace_id' => $this->user->active_workspace_id,
        'user_id' => $this->user->id,
        'tool_name' => 'list-projects',
        'transport' => 'stdio',
        'success' => true,
        'duration_ms' => 12,
        'started_at' => now()->subSecond(),
        'completed_at' => now(),
    ]);

    Livewire::test('pages::tool-invocations')
        ->assertSee('list-projects')
        ->assertDontSee('Surface')
        ->assertDontSee('Role');
});

test('tool invocations shows Surface and Role when an invocation is bound', function () {
    ToolInvocation::create([
        'workspace_id' => $this->user->active_workspace_id,
        'user_id' => $this->user->id,
        'tool_name' => 'list-projects',
        'transport' => 'stdio',
        'success' => true,
        'duration_ms' => 12,
        'started_at' => now()->subSecond(),
        'completed_at' => now(),
        'acting_surface' => 'intake',
        'acting_role_name' => 'Analyst',
    ]);

    Livewire::test('pages::tool-invocations')
        ->assertSee('Surface')
        ->assertSee('intake')
        ->assertSee('Role')
        ->assertSee('Analyst');
});

test('requirement detail omits the Properties card when source, parent and tags are all empty', function () {
    $requirement = $this->project->requirements()->create([
        'doc' => 'srs',
        'type' => 'functional',
        'text' => 'System shall ingest telemetry within 250ms.',
    ]);

    $this->get('/requirements/'.$requirement->id)
        ->assertOk()
        ->assertSee('ingest telemetry')
        ->assertDontSee('Properties');
});

test('requirement detail renders the Properties card when a property is present', function () {
    $requirement = $this->project->requirements()->create([
        'doc' => 'srs',
        'type' => 'functional',
        'text' => 'System shall ingest telemetry within 250ms.',
        'source' => 'Stakeholder review',
    ]);

    $this->get('/requirements/'.$requirement->id)
        ->assertOk()
        ->assertSee('Properties')
        ->assertSee('Stakeholder review');
});
