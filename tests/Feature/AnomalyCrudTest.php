<?php

use App\Models\Anomaly;
use App\Models\Project;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->project = Project::create([
        'workspace_id' => $this->user->active_workspace_id,
        'name' => 'Lunar Lander',
        'rigor_level' => 2,
    ]);
});

test('owner can create an anomaly via the modal', function () {
    $this->actingAs($this->user);

    Livewire::test('pages::anomalies.create-modal', ['projectId' => $this->project->id])
        ->set('summary', 'Telemetry drift')
        ->set('description', 'Subsecond drift between burst windows.')
        ->set('severity', 'high')
        ->set('status', 'open')
        ->set('environment', 'staging')
        ->call('save')
        ->assertHasNoErrors();

    $anomaly = Anomaly::query()->where('summary', 'Telemetry drift')->first();
    expect($anomaly)->not->toBeNull()
        ->and($anomaly->project_id)->toBe($this->project->id)
        ->and($anomaly->environment)->toBe('staging');
});

test('anomaly create requires summary and description', function () {
    $this->actingAs($this->user);

    Livewire::test('pages::anomalies.create-modal', ['projectId' => $this->project->id])
        ->set('summary', '')
        ->set('description', '')
        ->call('save')
        ->assertHasErrors(['summary' => 'required', 'description' => 'required']);
});

test('anomaly create projectId is locked', function () {
    $bob = User::factory()->create();
    $bobProject = Project::create([
        'workspace_id' => $bob->active_workspace_id,
        'name' => 'Hostile',
        'rigor_level' => 1,
    ]);

    $this->actingAs($this->user);

    expect(fn () => Livewire::test('pages::anomalies.create-modal', ['projectId' => $this->project->id])
        ->set('projectId', $bobProject->id))
        ->toThrow(Exception::class);
});

test('owner can edit an anomaly', function () {
    $anomaly = $this->project->anomalies()->create([
        'severity' => 'medium',
        'status' => 'open',
        'summary' => 'Glitch',
        'description' => 'A small glitch.',
    ]);

    $this->actingAs($this->user);

    Livewire::test('pages::anomalies.edit-modal', ['anomalyId' => $anomaly->id])
        ->set('status', 'resolved')
        ->set('description', 'Fixed via patch.')
        ->call('save')
        ->assertHasNoErrors();

    $anomaly->refresh();
    expect($anomaly->status)->toBe('resolved')
        ->and($anomaly->description)->toBe('Fixed via patch.');
});

test('anomaly edit 404s for another owner', function () {
    $bob = User::factory()->create();
    $bobProject = Project::create([
        'workspace_id' => $bob->active_workspace_id,
        'name' => 'Other',
        'rigor_level' => 1,
    ]);
    $bobAnomaly = $bobProject->anomalies()->create([
        'severity' => 'low',
        'status' => 'open',
        'summary' => 'x',
        'description' => 'x',
    ]);

    $this->actingAs($this->user);

    Livewire::test('pages::anomalies.edit-modal', ['anomalyId' => $bobAnomaly->id])
        ->assertStatus(404);
});

test('owner can delete an anomaly', function () {
    $anomaly = $this->project->anomalies()->create([
        'severity' => 'low',
        'status' => 'open',
        'summary' => 'x',
        'description' => 'x',
    ]);

    $this->actingAs($this->user);

    Livewire::test('pages::anomalies.delete-modal', ['anomalyId' => $anomaly->id])
        ->call('delete');

    expect(Anomaly::find($anomaly->id))->toBeNull();
});

test('anomaly delete 404s for another owner', function () {
    $bob = User::factory()->create();
    $bobProject = Project::create([
        'workspace_id' => $bob->active_workspace_id,
        'name' => 'Other',
        'rigor_level' => 1,
    ]);
    $bobAnomaly = $bobProject->anomalies()->create([
        'severity' => 'low',
        'status' => 'open',
        'summary' => 'x',
        'description' => 'x',
    ]);

    $this->actingAs($this->user);

    Livewire::test('pages::anomalies.delete-modal', ['anomalyId' => $bobAnomaly->id])
        ->call('delete')
        ->assertStatus(404);

    expect(Anomaly::withoutGlobalScopes()->find($bobAnomaly->id))->not->toBeNull();
});

test('verification page renders Report anomaly button', function () {
    $this->actingAs($this->user)
        ->get('/verification?project='.$this->project->id)
        ->assertOk()
        ->assertSee('Report anomaly');
});
