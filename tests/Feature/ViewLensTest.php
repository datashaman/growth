<?php

use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkspaceMembership;
use App\Support\Capability;

function projectForLens(User $user): Project
{
    $project = Project::create([
        'workspace_id' => $user->active_workspace_id,
        'name' => 'Lunar Lander',
        'rigor_level' => 2,
    ]);

    session(['selected_project_id' => $project->id]);

    return $project;
}

function assignRoleWithCapabilities(User $user, Project $project, array $capabilities): Role
{
    $role = Role::create([
        'project_id' => $project->id,
        'name' => fake()->unique()->jobTitle(),
    ]);

    $role->syncCapabilities($capabilities);
    $role->users()->attach($user);

    return $role;
}

it('shows every section to a workspace owner with no project role', function () {
    $user = User::factory()->create();
    projectForLens($user);

    expect($user->lens()->sections())->toEqualCanonicalizing([
        'dashboard',
        'intent',
        'requirements',
        'architecture',
        'plan',
        'verification',
        'changes',
        'reviews',
        'evidence',
    ]);
});

it('shows no project sections to a non-admin participant with no project role', function () {
    $owner = User::factory()->create();
    $project = projectForLens($owner);

    $viewer = User::factory()->create();
    WorkspaceMembership::create([
        'workspace_id' => $owner->active_workspace_id,
        'user_id' => $viewer->id,
        'role' => WorkspaceMembership::ROLE_VIEWER,
    ]);
    $viewer->switchWorkspace($owner->activeWorkspace);
    session(['selected_project_id' => $project->id]);

    expect($viewer->lens()->sections())->toBe([]);
});

it('keeps the see-all fallback for an owner whose only role has no capabilities', function () {
    $user = User::factory()->create();
    $project = projectForLens($user);

    assignRoleWithCapabilities($user, $project, []);

    expect($user->lens()->sections())->toEqualCanonicalizing([
        'dashboard',
        'intent',
        'requirements',
        'architecture',
        'plan',
        'verification',
        'changes',
        'reviews',
        'evidence',
    ]);
});

it('shows no project sections to a non-owner whose only role has no capabilities', function () {
    $owner = User::factory()->create();
    $project = projectForLens($owner);

    $viewer = User::factory()->create();
    WorkspaceMembership::create([
        'workspace_id' => $owner->active_workspace_id,
        'user_id' => $viewer->id,
        'role' => WorkspaceMembership::ROLE_VIEWER,
    ]);
    $viewer->switchWorkspace($owner->activeWorkspace);
    session(['selected_project_id' => $project->id]);

    assignRoleWithCapabilities($viewer, $project, []);

    expect($viewer->lens()->sections())->toBe([]);
});

it('derives sections from the assigned role capabilities', function () {
    $user = User::factory()->create();
    $project = projectForLens($user);

    assignRoleWithCapabilities($user, $project, [
        Capability::ManageIntent,
        Capability::ManageRequirements,
        Capability::ManageArchitecture,
        Capability::ViewDashboard,
    ]);

    expect($user->lens()->sections())->toEqualCanonicalizing([
        'dashboard',
        'intent',
        'requirements',
        'architecture',
    ]);
});

it('unions sections across multiple assigned roles', function () {
    $user = User::factory()->create();
    $project = projectForLens($user);

    assignRoleWithCapabilities($user, $project, [Capability::ManagePlan]);
    assignRoleWithCapabilities($user, $project, [Capability::ManageVerification, Capability::ViewEvidence]);

    expect($user->lens()->sections())->toEqualCanonicalizing([
        'plan',
        'verification',
        'evidence',
    ]);
});

it('shows only assigned capability sections in the sidebar', function () {
    $user = User::factory()->create();
    $project = projectForLens($user);
    assignRoleWithCapabilities($user, $project, [
        Capability::ManageIntent,
        Capability::ManageRequirements,
        Capability::ManageArchitecture,
        Capability::ViewDashboard,
    ]);

    $this->actingAs($user)
        ->get('/dashboard?project='.$project->id)
        ->assertOk()
        ->assertSee(route('dashboard'))
        ->assertSee(route('intent'))
        ->assertSee(route('requirements'))
        ->assertSee(route('architecture'))
        ->assertSee(route('roles'))
        ->assertDontSee(route('plan'))
        ->assertDontSee(route('verification'))
        ->assertDontSee(route('evidence'))
        ->assertDontSee(route('changes'));
});

it('always shows the Workspace group regardless of lens', function () {
    $user = User::factory()->create();
    $project = projectForLens($user);
    assignRoleWithCapabilities($user, $project, [Capability::ViewDashboard]);

    $this->actingAs($user)
        ->get('/dashboard?project='.$project->id)
        ->assertOk()
        ->assertSee(route('tool-invocations'))
        ->assertSee(route('feedback'));
});

it('still serves a section the active lens hides', function () {
    $user = User::factory()->create();
    $project = projectForLens($user);
    assignRoleWithCapabilities($user, $project, [Capability::ViewDashboard]);

    $this->actingAs($user)
        ->get('/plan?project='.$project->id)
        ->assertOk();
});

it('explains the empty Project nav to a non-owner whose role has no capabilities', function () {
    $owner = User::factory()->create();
    $project = projectForLens($owner);

    $viewer = User::factory()->create();
    WorkspaceMembership::create([
        'workspace_id' => $owner->active_workspace_id,
        'user_id' => $viewer->id,
        'role' => WorkspaceMembership::ROLE_VIEWER,
    ]);
    $viewer->switchWorkspace($owner->activeWorkspace);
    assignRoleWithCapabilities($viewer, $project, []);

    $this->actingAs($viewer)
        ->get('/dashboard?project='.$project->id)
        ->assertOk()
        ->assertSee('No role sections are visible yet')
        ->assertSee(route('roles'))
        ->assertDontSee(route('plan'))
        ->assertDontSee(route('requirements'));
});

it('shows the full Project nav and no hint to an owner whose role has no capabilities', function () {
    $user = User::factory()->create();
    $project = projectForLens($user);
    assignRoleWithCapabilities($user, $project, []);

    $this->actingAs($user)
        ->get('/dashboard?project='.$project->id)
        ->assertOk()
        ->assertDontSee('No role sections are visible yet')
        ->assertSee(route('dashboard'))
        ->assertSee(route('plan'))
        ->assertSee(route('roles'));
});
