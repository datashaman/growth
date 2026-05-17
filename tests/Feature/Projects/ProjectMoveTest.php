<?php

use App\Models\Project;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMembership;
use Symfony\Component\HttpKernel\Exception\HttpException;

function moveTestDestination(User $user, string $role = WorkspaceMembership::ROLE_OWNER): Workspace
{
    $workspace = Workspace::create([
        'name' => 'Dest '.uniqid(),
        'slug' => Workspace::uniqueSlug('dest-'.uniqid()),
        'owner_user_id' => $role === WorkspaceMembership::ROLE_OWNER ? $user->id : null,
    ]);

    WorkspaceMembership::create([
        'workspace_id' => $workspace->id,
        'user_id' => $user->id,
        'role' => $role,
        'last_accessed_at' => now()->subDay(),
    ]);

    return $workspace;
}

function moveTestProject(string $workspaceId, string $name = 'Mover'): Project
{
    return Project::create([
        'workspace_id' => $workspaceId,
        'name' => $name,
        'rigor_level' => 2,
    ]);
}

test('owner can move project to another workspace where they are owner', function () {
    $user = User::factory()->create();
    $project = moveTestProject($user->active_workspace_id);
    $destination = moveTestDestination($user);

    $project->move($destination, $user);

    expect(Project::withoutGlobalScope('workspace')->find($project->id)->workspace_id)
        ->toBe($destination->id);
});

test('admin can move project to another workspace where they are admin', function () {
    $sourceOwner = User::factory()->create();
    $destOwner = User::factory()->create();

    $admin = User::factory()->create();
    WorkspaceMembership::create([
        'workspace_id' => $sourceOwner->active_workspace_id,
        'user_id' => $admin->id,
        'role' => WorkspaceMembership::ROLE_ADMIN,
        'last_accessed_at' => now(),
    ]);
    WorkspaceMembership::create([
        'workspace_id' => $destOwner->active_workspace_id,
        'user_id' => $admin->id,
        'role' => WorkspaceMembership::ROLE_ADMIN,
        'last_accessed_at' => now()->subDay(),
    ]);

    $project = moveTestProject($sourceOwner->active_workspace_id);

    $project->move($destOwner->active_workspace_id, $admin);

    expect(Project::withoutGlobalScope('workspace')->find($project->id)->workspace_id)
        ->toBe($destOwner->active_workspace_id);
});

test('viewer in source cannot move', function () {
    $owner = User::factory()->create();
    $viewer = User::factory()->create();
    WorkspaceMembership::create([
        'workspace_id' => $owner->active_workspace_id,
        'user_id' => $viewer->id,
        'role' => WorkspaceMembership::ROLE_VIEWER,
        'last_accessed_at' => now(),
    ]);
    $destination = moveTestDestination($viewer);
    $project = moveTestProject($owner->active_workspace_id);

    expect(fn () => $project->move($destination, $viewer))
        ->toThrow(HttpException::class);

    expect(Project::withoutGlobalScope('workspace')->find($project->id)->workspace_id)
        ->toBe($owner->active_workspace_id);
});

test('viewer in destination cannot move', function () {
    $user = User::factory()->create();
    $destOwner = User::factory()->create();

    WorkspaceMembership::create([
        'workspace_id' => $destOwner->active_workspace_id,
        'user_id' => $user->id,
        'role' => WorkspaceMembership::ROLE_VIEWER,
        'last_accessed_at' => now()->subDay(),
    ]);

    $project = moveTestProject($user->active_workspace_id);

    expect(fn () => $project->move($destOwner->active_workspace_id, $user))
        ->toThrow(HttpException::class);
});

test('destination must differ from source', function () {
    $user = User::factory()->create();
    $project = moveTestProject($user->active_workspace_id);

    expect(fn () => $project->move($user->active_workspace_id, $user))
        ->toThrow(HttpException::class);
});
