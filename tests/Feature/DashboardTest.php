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
        'integrity_level' => 3,
    ]);

    $this->actingAs($user)
        ->get('/dashboard?project='.$project->id)
        ->assertOk()
        ->assertSee('Lunar Lander')
        ->assertSee('Mission control.')
        ->assertSee('Counts')
        ->assertSee('Stakeholders')
        ->assertSee('Readiness')
        ->assertSee('Schedule health');
});

test('dashboard only lists projects owned by the authed user', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    Project::create([
        'user_id' => $alice->id,
        'name' => 'Alice project',
        'integrity_level' => 1,
    ]);
    Project::create([
        'user_id' => $bob->id,
        'name' => 'Bob project',
        'integrity_level' => 1,
    ]);

    $this->actingAs($alice)
        ->get('/dashboard')
        ->assertOk()
        ->assertSee('Alice project')
        ->assertDontSee('Bob project');
});
