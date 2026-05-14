<?php

use App\Models\Project;
use App\Models\User;

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
        'user_id' => $user->id,
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
        ->assertSee('Schedule health')
        ->assertSee('Implementation')
        ->assertSee('Capacity')
        ->assertSee('Risks')
        ->assertSee('Anomalies')
        ->assertSee('Reviews');
});

test('dashboard implementation table lists work items', function () {
    $user = User::factory()->create();
    $project = Project::create([
        'user_id' => $user->id,
        'name' => 'Lunar Lander',
        'rigor_level' => 2,
    ]);
    $project->workItems()->create([
        'kind' => 'task',
        'name' => 'Wire the descent engine',
        'status' => 'done',
        'effort_estimate_hours' => 8,
    ]);

    $this->actingAs($user)
        ->get('/dashboard?project='.$project->id)
        ->assertOk()
        ->assertSee('Wire the descent engine')
        ->assertSee('done without evidence');
});

test('dashboard capacity table groups work items by role', function () {
    $user = User::factory()->create();
    $project = Project::create([
        'user_id' => $user->id,
        'name' => 'Lunar Lander',
        'rigor_level' => 2,
    ]);
    $project->workItems()->create([
        'kind' => 'task',
        'name' => 'Unassigned work',
        'status' => 'todo',
        'effort_estimate_hours' => 4,
    ]);

    $this->actingAs($user)
        ->get('/dashboard?project='.$project->id)
        ->assertOk()
        ->assertSee('Capacity')
        ->assertSee('Unassigned');
});

test('dashboard surfaces risks, anomalies, and reviews', function () {
    $user = User::factory()->create();
    $project = Project::create([
        'user_id' => $user->id,
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

test('dashboard only lists projects owned by the authed user', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    Project::create([
        'user_id' => $alice->id,
        'name' => 'Alice project',
        'rigor_level' => 1,
    ]);
    Project::create([
        'user_id' => $bob->id,
        'name' => 'Bob project',
        'rigor_level' => 1,
    ]);

    $this->actingAs($alice)
        ->get('/dashboard')
        ->assertOk()
        ->assertSee('Alice project')
        ->assertDontSee('Bob project');
});
