<?php

use App\Models\Project;
use App\Models\Risk;
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

test('owner can create a risk via the modal', function () {
    $role = $this->project->roles()->create(['name' => 'Propulsion Engineer']);

    $this->actingAs($this->user);

    Livewire::test('pages::risks.create-modal', ['projectId' => $this->project->id])
        ->set('title', 'Cryogenic seal failure')
        ->set('description', 'O-ring may stiffen below -20C.')
        ->set('category', 'technical')
        ->set('probability', 'medium')
        ->set('impact', 'high')
        ->set('status', 'identified')
        ->set('owner_role_id', $role->id)
        ->set('mitigation_plan', 'Add heater band to seal area.')
        ->call('save')
        ->assertHasNoErrors();

    $risk = Risk::query()->where('title', 'Cryogenic seal failure')->first();
    expect($risk)->not->toBeNull()
        ->and($risk->project_id)->toBe($this->project->id)
        ->and($risk->owner_role_id)->toBe($role->id)
        ->and($risk->mitigation_plan)->toBe('Add heater band to seal area.');
});

test('title is required', function () {
    $this->actingAs($this->user);

    Livewire::test('pages::risks.create-modal', ['projectId' => $this->project->id])
        ->set('title', '')
        ->call('save')
        ->assertHasErrors(['title' => 'required']);
});

test('category must be from the allowed list', function () {
    $this->actingAs($this->user);

    Livewire::test('pages::risks.create-modal', ['projectId' => $this->project->id])
        ->set('title', 'X')
        ->set('category', 'not-a-real-category')
        ->call('save')
        ->assertHasErrors(['category']);
});

test('owner_role_id from another project is rejected', function () {
    $otherUser = User::factory()->create();
    $otherProject = Project::create([
        'workspace_id' => $otherUser->active_workspace_id,
        'name' => 'Other',
        'rigor_level' => 1,
    ]);
    $foreignRole = $otherProject->roles()->create(['name' => 'Spy']);

    $this->actingAs($this->user);

    Livewire::test('pages::risks.create-modal', ['projectId' => $this->project->id])
        ->set('title', 'X')
        ->set('owner_role_id', $foreignRole->id)
        ->call('save')
        ->assertHasErrors(['owner_role_id']);

    expect(Risk::query()->count())->toBe(0);
});

test('project_id is locked and cannot be set from the client', function () {
    $bob = User::factory()->create();
    $bobProject = Project::create([
        'workspace_id' => $bob->active_workspace_id,
        'name' => 'Hostile',
        'rigor_level' => 1,
    ]);

    $this->actingAs($this->user);

    expect(fn () => Livewire::test('pages::risks.create-modal', ['projectId' => $this->project->id])
        ->set('projectId', $bobProject->id))
        ->toThrow(Exception::class);
});

test('role options are scoped to the selected project', function () {
    $ownRole = $this->project->roles()->create(['name' => 'My Role']);

    $otherUser = User::factory()->create();
    $otherProject = Project::create([
        'workspace_id' => $otherUser->active_workspace_id,
        'name' => 'Other',
        'rigor_level' => 1,
    ]);
    $otherProject->roles()->create(['name' => 'Foreign Role']);

    $this->actingAs($this->user);

    $component = Livewire::test('pages::risks.create-modal', ['projectId' => $this->project->id]);
    $options = $component->instance()->roleOptions;

    expect($options->pluck('id')->all())->toBe([$ownRole->id]);
});

test('dashboard renders the New risk button for project owner', function () {
    $this->actingAs($this->user)
        ->get('/dashboard?project='.$this->project->id)
        ->assertOk()
        ->assertSee('New risk');
});
