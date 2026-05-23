<?php

use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkspaceMembership;
use App\Support\Capability;
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

it('shows assigned users in the roles table Users column', function () {
    $jennifer = User::factory()->create(['name' => 'Jennifer Walker']);
    $role = Role::create(['project_id' => $this->project->id, 'name' => 'Product Lead']);
    $role->users()->attach($jennifer);

    Livewire::test('pages::roles')
        ->assertSee('Product Lead')
        ->assertSee('Jennifer Walker');
});

it('renders the roles table for a role with no assigned users', function () {
    Role::create(['project_id' => $this->project->id, 'name' => 'Unfilled Role']);

    Livewire::test('pages::roles')
        ->assertOk()
        ->assertSee('Unfilled Role');
});

it('lets workspace mutators assign role capabilities from the roles page', function () {
    $role = Role::create(['project_id' => $this->project->id, 'name' => 'Engineering Lead']);

    Livewire::test('pages::roles')
        ->call('toggleRoleCapability', $role->id, Capability::ManagePlan->value)
        ->call('toggleRoleCapability', $role->id, Capability::ViewDashboard->value)
        ->assertDispatched('role-capabilities-updated')
        ->assertOk();

    expect($role->fresh()->load('capabilityAssignments')->capabilities()->map->value->all())
        ->toEqualCanonicalizing([
            Capability::ManagePlan->value,
            Capability::ViewDashboard->value,
        ]);
});

it('lets workspace mutators remove role capabilities from the roles page', function () {
    $role = Role::create(['project_id' => $this->project->id, 'name' => 'Engineering Lead']);
    $role->syncCapabilities([Capability::ManagePlan, Capability::ViewDashboard]);

    Livewire::test('pages::roles')
        ->call('toggleRoleCapability', $role->id, Capability::ManagePlan->value)
        ->assertDispatched('role-capabilities-updated')
        ->assertOk();

    expect($role->fresh()->load('capabilityAssignments')->capabilities()->map->value->all())
        ->toEqual([Capability::ViewDashboard->value]);
});

it('blocks workspace viewers from assigning role capabilities from the roles page', function () {
    $viewer = User::factory()->create();
    WorkspaceMembership::create([
        'workspace_id' => $this->project->workspace_id,
        'user_id' => $viewer->id,
        'role' => WorkspaceMembership::ROLE_VIEWER,
    ]);
    $viewer->switchWorkspace($this->user->activeWorkspace);
    $this->actingAs($viewer);
    session(['selected_project_id' => $this->project->id]);

    $role = Role::create(['project_id' => $this->project->id, 'name' => 'Engineering Lead']);

    Livewire::test('pages::roles')
        ->call('toggleRoleCapability', $role->id, Capability::ManagePlan->value)
        ->assertForbidden();

    expect($role->fresh()->load('capabilityAssignments')->capabilities())->toBeEmpty();
});
