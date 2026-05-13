<?php

use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->project = Project::create([
        'user_id' => $this->user->id,
        'name' => 'Lunar Lander',
        'integrity_level' => 2,
    ]);
});

test('owner can create a role', function () {
    $this->actingAs($this->user);

    Livewire::test('pages::roles.create-modal', ['projectId' => $this->project->id])
        ->set('name', 'Thermal Lead')
        ->set('responsibilities', 'Owns thermal protection system.')
        ->set('weekly_capacity_hours', '32')
        ->set('hourly_rate_amount', '180')
        ->set('rate_currency', 'USD')
        ->call('save')
        ->assertHasNoErrors();

    $role = Role::query()->where('name', 'Thermal Lead')->first();
    expect($role)->not->toBeNull()
        ->and($role->project_id)->toBe($this->project->id)
        ->and((float) $role->weekly_capacity_hours)->toBe(32.0);
});

test('role create requires name', function () {
    $this->actingAs($this->user);

    Livewire::test('pages::roles.create-modal', ['projectId' => $this->project->id])
        ->set('name', '')
        ->call('save')
        ->assertHasErrors(['name' => 'required']);
});

test('role name must be unique within a project', function () {
    $this->project->roles()->create(['name' => 'Lead']);
    $this->actingAs($this->user);

    Livewire::test('pages::roles.create-modal', ['projectId' => $this->project->id])
        ->set('name', 'Lead')
        ->call('save')
        ->assertHasErrors('name');
});

test('role name uniqueness is scoped to the project', function () {
    $otherUser = User::factory()->create();
    $otherProject = Project::create([
        'user_id' => $otherUser->id,
        'name' => 'Other',
        'integrity_level' => 1,
    ]);
    $otherProject->roles()->create(['name' => 'Lead']);
    $this->actingAs($this->user);

    Livewire::test('pages::roles.create-modal', ['projectId' => $this->project->id])
        ->set('name', 'Lead')
        ->call('save')
        ->assertHasNoErrors();
});

test('role create projectId is locked', function () {
    $bob = User::factory()->create();
    $bobProject = Project::create([
        'user_id' => $bob->id,
        'name' => 'Hostile',
        'integrity_level' => 1,
    ]);
    $this->actingAs($this->user);

    expect(fn () => Livewire::test('pages::roles.create-modal', ['projectId' => $this->project->id])
        ->set('projectId', $bobProject->id))
        ->toThrow(Exception::class);
});

test('owner can edit a role', function () {
    $role = $this->project->roles()->create(['name' => 'Old name']);
    $this->actingAs($this->user);

    Livewire::test('pages::roles.edit-modal')
        ->call('load', $role->id)
        ->set('name', 'New name')
        ->call('save')
        ->assertHasNoErrors();

    expect($role->fresh()->name)->toBe('New name');
});

test('role edit 404s for another owner', function () {
    $bob = User::factory()->create();
    $bobProject = Project::create([
        'user_id' => $bob->id,
        'name' => 'Other',
        'integrity_level' => 1,
    ]);
    $bobRole = $bobProject->roles()->create(['name' => 'Spy']);
    $this->actingAs($this->user);

    Livewire::test('pages::roles.edit-modal')
        ->call('load', $bobRole->id)
        ->assertStatus(404);
});

test('owner can delete a role', function () {
    $role = $this->project->roles()->create(['name' => 'Doomed']);
    $this->actingAs($this->user);

    Livewire::test('pages::roles.delete-modal')
        ->call('load', $role->id)
        ->call('delete');

    expect(Role::find($role->id))->toBeNull();
});

test('role delete 404s for another owner', function () {
    $bob = User::factory()->create();
    $bobProject = Project::create([
        'user_id' => $bob->id,
        'name' => 'Other',
        'integrity_level' => 1,
    ]);
    $bobRole = $bobProject->roles()->create(['name' => 'Spy']);
    $this->actingAs($this->user);

    Livewire::test('pages::roles.delete-modal')
        ->call('load', $bobRole->id)
        ->assertStatus(404);

    expect(Role::withoutGlobalScopes()->find($bobRole->id))->not->toBeNull();
});

test('delete modal surfaces usage warnings', function () {
    $role = $this->project->roles()->create(['name' => 'Busy']);
    $this->project->workItems()->create([
        'kind' => 'task', 'name' => 'wi', 'status' => 'todo',
        'responsible_role_id' => $role->id,
    ]);
    $this->actingAs($this->user);

    Livewire::test('pages::roles.delete-modal')
        ->call('load', $role->id)
        ->assertSet('workItemCount', 1);
});

test('plan page renders New role button', function () {
    $this->actingAs($this->user)
        ->get('/plan?project='.$this->project->id)
        ->assertOk()
        ->assertSee('New role');
});
