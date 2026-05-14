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
    $this->risk = $this->project->risks()->create([
        'title' => 'Heat shield delamination',
        'description' => 'Original description.',
        'category' => 'technical',
        'probability' => 'high',
        'impact' => 'high',
        'status' => 'identified',
    ]);
});

test('owner can edit a risk via the modal', function () {
    $role = $this->project->roles()->create(['name' => 'Thermal Lead']);

    $this->actingAs($this->user);

    Livewire::test('pages::risks.edit-modal', ['riskId' => $this->risk->id])
        ->assertSet('title', 'Heat shield delamination')
        ->set('title', 'Heat shield ablation')
        ->set('status', 'mitigating')
        ->set('owner_role_id', $role->id)
        ->set('mitigation_plan', 'Add ablator layer.')
        ->call('save')
        ->assertHasNoErrors();

    $this->risk->refresh();
    expect($this->risk->title)->toBe('Heat shield ablation')
        ->and($this->risk->status)->toBe('mitigating')
        ->and($this->risk->owner_role_id)->toBe($role->id)
        ->and($this->risk->mitigation_plan)->toBe('Add ablator layer.');
});

test('edit modal 404s for a risk in another project', function () {
    $bob = User::factory()->create();
    $bobProject = Project::create([
        'workspace_id' => $bob->active_workspace_id,
        'name' => 'Other',
        'rigor_level' => 1,
    ]);
    $bobRisk = $bobProject->risks()->create([
        'title' => "Bob's risk",
        'category' => 'technical',
        'probability' => 'low',
        'impact' => 'low',
        'status' => 'identified',
    ]);

    $this->actingAs($this->user);

    Livewire::test('pages::risks.edit-modal', ['riskId' => $bobRisk->id])
        ->assertStatus(404);
});

test('edit rejects owner_role_id from a different project', function () {
    $otherUser = User::factory()->create();
    $otherProject = Project::create([
        'workspace_id' => $otherUser->active_workspace_id,
        'name' => 'Other',
        'rigor_level' => 1,
    ]);
    $foreignRole = $otherProject->roles()->create(['name' => 'Spy']);

    $this->actingAs($this->user);

    Livewire::test('pages::risks.edit-modal', ['riskId' => $this->risk->id])
        ->set('owner_role_id', $foreignRole->id)
        ->call('save')
        ->assertHasErrors(['owner_role_id']);

    $this->risk->refresh();
    expect($this->risk->owner_role_id)->toBeNull();
});

test('riskId is locked', function () {
    $otherRisk = $this->project->risks()->create([
        'title' => 'Other',
        'category' => 'technical',
        'probability' => 'low',
        'impact' => 'low',
        'status' => 'identified',
    ]);

    $this->actingAs($this->user);

    expect(fn () => Livewire::test('pages::risks.edit-modal', ['riskId' => $this->risk->id])
        ->set('riskId', $otherRisk->id))
        ->toThrow(Exception::class);
});

test('owner can delete a risk via the modal', function () {
    $this->actingAs($this->user);

    Livewire::test('pages::risks.delete-modal', ['riskId' => $this->risk->id])
        ->call('delete');

    expect(Risk::query()->find($this->risk->id))->toBeNull();
});

test('delete modal 404s for a risk in another project', function () {
    $bob = User::factory()->create();
    $bobProject = Project::create([
        'workspace_id' => $bob->active_workspace_id,
        'name' => 'Other',
        'rigor_level' => 1,
    ]);
    $bobRisk = $bobProject->risks()->create([
        'title' => "Bob's risk",
        'category' => 'technical',
        'probability' => 'low',
        'impact' => 'low',
        'status' => 'identified',
    ]);

    $this->actingAs($this->user);

    Livewire::test('pages::risks.delete-modal', ['riskId' => $bobRisk->id])
        ->call('delete')
        ->assertStatus(404);

    expect(Risk::withoutGlobalScopes()->find($bobRisk->id))->not->toBeNull();
});

test('risk detail page renders Edit and Delete buttons for the owner', function () {
    $this->actingAs($this->user)
        ->get('/risks/'.$this->risk->id)
        ->assertOk()
        ->assertSee('Edit')
        ->assertSee('Delete');
});
