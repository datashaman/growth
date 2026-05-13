<?php

use App\Models\Project;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->project = Project::create([
        'user_id' => $this->user->id,
        'name' => 'Lunar Lander',
        'integrity_level' => 2,
    ]);
});

test('work item detail renders for the owner', function () {
    $role = $this->project->roles()->create(['name' => 'Propulsion Engineer']);
    $workItem = $this->project->workItems()->create([
        'kind' => 'task',
        'name' => 'Wire the descent engine',
        'status' => 'in_progress',
        'responsible_role_id' => $role->id,
        'description' => 'Hook up ignition harness to the descent stage.',
    ]);

    $this->actingAs($this->user)
        ->get('/work-items/'.$workItem->id)
        ->assertOk()
        ->assertSee('Wire the descent engine')
        ->assertSee('Hook up ignition harness')
        ->assertSee('Propulsion Engineer');
});

test('work item detail 404s for non-owner', function () {
    $workItem = $this->project->workItems()->create([
        'kind' => 'task',
        'name' => 'Wire the descent engine',
        'status' => 'todo',
    ]);
    $bob = User::factory()->create();

    $this->actingAs($bob)
        ->get('/work-items/'.$workItem->id)
        ->assertNotFound();
});

test('requirement detail renders for the owner', function () {
    $requirement = $this->project->requirements()->create([
        'doc' => 'srs',
        'type' => 'functional',
        'text' => 'System shall ignite descent engine at T-10s.',
        'rationale' => 'Required for nominal landing profile.',
        'priority' => 'high',
        'source' => 'Stakeholder review',
    ]);

    $this->actingAs($this->user)
        ->get('/requirements/'.$requirement->id)
        ->assertOk()
        ->assertSee('ignite descent engine')
        ->assertSee('Required for nominal')
        ->assertSee('Stakeholder review')
        ->assertSee('SRS');
});

test('requirement detail 404s for non-owner', function () {
    $requirement = $this->project->requirements()->create([
        'doc' => 'srs',
        'type' => 'functional',
        'text' => 'System shall ignite descent engine.',
    ]);
    $bob = User::factory()->create();

    $this->actingAs($bob)
        ->get('/requirements/'.$requirement->id)
        ->assertNotFound();
});

test('anomaly detail renders for the owner', function () {
    $anomaly = $this->project->anomalies()->create([
        'severity' => 'high',
        'status' => 'open',
        'summary' => 'Telemetry sync drift',
        'description' => 'Subseconds desync between burst windows.',
        'environment' => 'staging',
    ]);

    $this->actingAs($this->user)
        ->get('/anomalies/'.$anomaly->id)
        ->assertOk()
        ->assertSee('Telemetry sync drift')
        ->assertSee('Subseconds desync')
        ->assertSee('staging');
});

test('anomaly detail 404s for non-owner', function () {
    $anomaly = $this->project->anomalies()->create([
        'severity' => 'low',
        'status' => 'open',
        'summary' => 'Minor glitch',
        'description' => 'Trivial.',
    ]);
    $bob = User::factory()->create();

    $this->actingAs($bob)
        ->get('/anomalies/'.$anomaly->id)
        ->assertNotFound();
});

test('review detail renders for the owner', function () {
    $review = $this->project->reviews()->create([
        'type' => 'technical_review',
        'title' => 'Heat shield design review',
        'status' => 'held',
        'decision' => 'accepted_with_actions',
        'objective' => 'Confirm thermal margins for re-entry.',
        'held_at' => now()->subDay(),
    ]);

    $this->actingAs($this->user)
        ->get('/reviews/'.$review->id)
        ->assertOk()
        ->assertSee('Heat shield design review')
        ->assertSee('Confirm thermal margins')
        ->assertSee('accepted with actions')
        ->assertSee('held');
});

test('review detail 404s for non-owner', function () {
    $review = $this->project->reviews()->create([
        'type' => 'technical_review',
        'title' => 'Heat shield design review',
        'status' => 'planned',
    ]);
    $bob = User::factory()->create();

    $this->actingAs($bob)
        ->get('/reviews/'.$review->id)
        ->assertNotFound();
});

test('detail pages redirect guests to login', function () {
    $workItem = $this->project->workItems()->create([
        'kind' => 'task',
        'name' => 'x',
        'status' => 'todo',
    ]);
    $req = $this->project->requirements()->create([
        'doc' => 'srs',
        'type' => 'functional',
        'text' => 'x',
    ]);
    $anomaly = $this->project->anomalies()->create([
        'severity' => 'low',
        'status' => 'open',
        'summary' => 'x',
        'description' => 'x',
    ]);
    $review = $this->project->reviews()->create([
        'type' => 'technical_review',
        'title' => 'x',
        'status' => 'planned',
    ]);

    $this->get('/work-items/'.$workItem->id)->assertRedirect('/login');
    $this->get('/requirements/'.$req->id)->assertRedirect('/login');
    $this->get('/anomalies/'.$anomaly->id)->assertRedirect('/login');
    $this->get('/reviews/'.$review->id)->assertRedirect('/login');
});
