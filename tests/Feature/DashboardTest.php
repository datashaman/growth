<?php

use App\Models\Project;
use App\Models\User;
use App\Support\ViewLens;

/**
 * Create a project whose dashboard exercises every panel: counts, readiness,
 * implementation, risks, anomalies, and reviews.
 */
function dashboardProjectWithEveryPanel(User $user): Project
{
    $project = Project::create([
        'workspace_id' => $user->active_workspace_id,
        'name' => 'Lunar Lander',
        'rigor_level' => 2,
    ]);

    $project->workItems()->create([
        'kind' => 'task',
        'name' => 'Wire the descent engine',
        'status' => 'todo',
    ]);
    $project->risks()->create([
        'title' => 'Heat shield delamination',
        'category' => 'technical',
        'probability' => 'high',
        'impact' => 'high',
        'status' => 'mitigating',
    ]);
    $project->anomalies()->create([
        'severity' => 'high',
        'status' => 'open',
        'summary' => 'Telemetry sync drift',
        'description' => 'Subseconds desync between burst windows.',
        'environment' => 'staging',
    ]);
    $project->reviews()->create([
        'type' => 'technical_review',
        'title' => 'Heat shield design review',
        'status' => 'held',
        'decision' => 'accepted_with_actions',
        'held_at' => now()->subDay(),
    ]);

    return $project;
}

test('dashboard redirects guests to login', function () {
    $this->get('/dashboard')->assertRedirect('/login');
});

test('dashboard renders for an authed user with no projects', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertOk()
        ->assertSee('Project Dashboard')
        ->assertSee('No projects yet');
});

test('dashboard renders sections for the selected project', function () {
    $user = User::factory()->create();
    $project = Project::create([
        'workspace_id' => $user->active_workspace_id,
        'name' => 'Lunar Lander',
        'description' => 'Mission control.',
        'rigor_level' => 3,
    ]);

    $this->actingAs($user)
        ->get('/dashboard?project='.$project->id)
        ->assertOk()
        ->assertSee('Lunar Lander')
        ->assertSee('Mission control.')
        ->assertSee('Counts')
        ->assertSee('Stakeholders')
        ->assertSee('Readiness')
        ->assertSee('Implementation')
        ->assertSee('Risks')
        ->assertSee('Anomalies')
        ->assertSee('Reviews');
});

test('dashboard readiness gates render their findings inline', function () {
    $user = User::factory()->create();
    $project = Project::create([
        'workspace_id' => $user->active_workspace_id,
        'name' => 'Lunar Lander',
        'rigor_level' => 2,
    ]);
    $project->requirements()->create([
        'doc' => 'srs',
        'type' => 'functional',
        'text' => 'The system shall do something TBD.',
    ]);

    $this->actingAs($user)
        ->get('/dashboard?project='.$project->id)
        ->assertOk()
        ->assertSee('Readiness')
        ->assertSee('rule: requirement contains TBD/TBS/TBR — not complete')
        ->assertSee('aria-controls="gate-findings-requirements"', false)
        ->assertSee('id="gate-findings-requirements"', false);
});

test('dashboard implementation table lists work items', function () {
    $user = User::factory()->create();
    $project = Project::create([
        'workspace_id' => $user->active_workspace_id,
        'name' => 'Lunar Lander',
        'rigor_level' => 2,
    ]);
    $project->workItems()->create([
        'kind' => 'task',
        'name' => 'Wire the descent engine',
        'status' => 'done',
    ]);

    $this->actingAs($user)
        ->get('/dashboard?project='.$project->id)
        ->assertOk()
        ->assertSee('Wire the descent engine')
        ->assertSee('done without evidence');
});

test('dashboard surfaces risks, anomalies, and reviews', function () {
    $user = User::factory()->create();
    $project = Project::create([
        'workspace_id' => $user->active_workspace_id,
        'name' => 'Lunar Lander',
        'rigor_level' => 2,
    ]);

    $project->risks()->create([
        'title' => 'Heat shield delamination',
        'category' => 'technical',
        'probability' => 'high',
        'impact' => 'high',
        'status' => 'mitigating',
    ]);
    $project->anomalies()->create([
        'severity' => 'high',
        'status' => 'open',
        'summary' => 'Telemetry sync drift',
        'description' => 'Subseconds desync between burst windows.',
        'environment' => 'staging',
    ]);
    $project->reviews()->create([
        'type' => 'technical_review',
        'title' => 'Heat shield design review',
        'status' => 'held',
        'decision' => 'accepted_with_actions',
        'held_at' => now()->subDay(),
    ]);

    $this->actingAs($user)
        ->get('/dashboard?project='.$project->id)
        ->assertOk()
        ->assertSee('Heat shield delamination')
        ->assertSee('Telemetry sync drift')
        ->assertSee('Heat shield design review')
        ->assertSee('accepted with actions');
});

/*
 * Each panel is identified by a string unique to it: a section heading
 * ("Counts") or a table column header ("Gate"
 * for readiness, "Deploys" for implementation, "Exposure" for risks,
 * "Environment" for anomalies, "Decision" for reviews). Neither entity titles
 * nor the word "Implementation" are used as anchors — the readiness panel
 * cross-references risks, anomalies, reviews, and work items as finding
 * subjects, and lists an "Implementation" readiness gate, so those strings can
 * surface outside their own panels.
 */

test('the All lens renders every dashboard panel', function () {
    $user = User::factory()->create();
    $user->switchLens(ViewLens::All);
    $project = dashboardProjectWithEveryPanel($user);

    $this->actingAs($user)
        ->get('/dashboard?project='.$project->id)
        ->assertOk()
        ->assertSee('Counts')
        ->assertSee('Gate')
        ->assertSee('Deploys')
        ->assertSee('Exposure')
        ->assertSee('Environment')
        ->assertSee('Decision');
});

test('the spec-writer lens renders only counts and readiness', function () {
    $user = User::factory()->create();
    $user->switchLens(ViewLens::SpecWriter);
    $project = dashboardProjectWithEveryPanel($user);

    $this->actingAs($user)
        ->get('/dashboard?project='.$project->id)
        ->assertOk()
        ->assertSee('Counts')
        ->assertSee('Gate')
        ->assertDontSee('Deploys')
        ->assertDontSee('Exposure')
        ->assertDontSee('Environment')
        ->assertDontSee('Decision');
});

test('the spec-implementer lens renders implementation, risks, and anomalies', function () {
    $user = User::factory()->create();
    $user->switchLens(ViewLens::SpecImplementer);
    $project = dashboardProjectWithEveryPanel($user);

    $this->actingAs($user)
        ->get('/dashboard?project='.$project->id)
        ->assertOk()
        ->assertSee('Deploys')
        ->assertSee('Exposure')
        ->assertSee('Environment')
        ->assertDontSee('Counts')
        ->assertDontSee('Gate')
        ->assertDontSee('Decision');
});

test('the reviewer lens renders only readiness and reviews', function () {
    $user = User::factory()->create();
    $user->switchLens(ViewLens::Reviewer);
    $project = dashboardProjectWithEveryPanel($user);

    $this->actingAs($user)
        ->get('/dashboard?project='.$project->id)
        ->assertOk()
        ->assertSee('Gate')
        ->assertSee('Decision')
        ->assertDontSee('Counts')
        ->assertDontSee('Deploys')
        ->assertDontSee('Exposure')
        ->assertDontSee('Environment');
});

test('dashboard only lists projects owned by the authed user', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    Project::create([
        'workspace_id' => $alice->active_workspace_id,
        'name' => 'Alice project',
        'rigor_level' => 1,
    ]);
    Project::create([
        'workspace_id' => $bob->active_workspace_id,
        'name' => 'Bob project',
        'rigor_level' => 1,
    ]);

    $this->actingAs($alice)
        ->get('/dashboard')
        ->assertOk()
        ->assertSee('Alice project')
        ->assertDontSee('Bob project');
});
