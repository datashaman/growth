<?php

use App\Models\Project;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;
use App\Models\WorkspaceMembership;
use Livewire\Livewire;

function makeSecondaryWorkspace(User $user, string $role = WorkspaceMembership::ROLE_OWNER): Workspace
{
    $workspace = Workspace::create([
        'name' => 'Other '.fake()->unique()->word(),
        'slug' => Workspace::uniqueSlug('other-'.uniqid()),
        'owner_user_id' => $role === WorkspaceMembership::ROLE_OWNER ? $user->id : null,
    ]);

    WorkspaceMembership::create([
        'workspace_id' => $workspace->id,
        'user_id' => $user->id,
        'role' => $role,
        'last_accessed_at' => now()->subMinutes(10),
    ]);

    return $workspace;
}

test('last owner with only one workspace cannot leave', function () {
    $owner = User::factory()->create();
    $this->actingAs($owner);

    Livewire::test('pages::settings.workspace')
        ->call('leave');

    expect(WorkspaceMembership::where('user_id', $owner->id)->count())->toBe(1);
});

test('last owner with another workspace cannot leave the current one', function () {
    $owner = User::factory()->create();
    makeSecondaryWorkspace($owner);

    $this->actingAs($owner);

    $originalActive = $owner->active_workspace_id;

    Livewire::test('pages::settings.workspace')
        ->call('leave');

    expect(WorkspaceMembership::where('user_id', $owner->id)
        ->where('workspace_id', $originalActive)
        ->exists())->toBeTrue();
});

test('member with another workspace can leave; active flips to most recently used', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();

    WorkspaceMembership::where('user_id', $member->id)
        ->update(['last_accessed_at' => now()->subDays(30)]);

    WorkspaceMembership::create([
        'workspace_id' => $owner->active_workspace_id,
        'user_id' => $member->id,
        'role' => WorkspaceMembership::ROLE_MEMBER,
        'last_accessed_at' => now(),
    ]);
    $member->forceFill(['active_workspace_id' => $owner->active_workspace_id])->save();

    $older = makeSecondaryWorkspace($member);
    WorkspaceMembership::where('user_id', $member->id)
        ->where('workspace_id', $older->id)
        ->update(['last_accessed_at' => now()->subDays(5)]);

    $newer = makeSecondaryWorkspace($member);
    WorkspaceMembership::where('user_id', $member->id)
        ->where('workspace_id', $newer->id)
        ->update(['last_accessed_at' => now()->subHour()]);

    $this->actingAs($member);

    Livewire::test('pages::settings.workspace')
        ->call('leave');

    expect(WorkspaceMembership::where('user_id', $member->id)
        ->where('workspace_id', $owner->active_workspace_id)
        ->exists())->toBeFalse();

    expect($member->fresh()->active_workspace_id)->toBe($newer->id);
});

test('member with only one workspace cannot leave', function () {
    $member = User::factory()->create();
    $this->actingAs($member);

    Livewire::test('pages::settings.workspace')
        ->call('leave');

    expect(WorkspaceMembership::where('user_id', $member->id)->count())->toBe(1);
});

test('non-owner cannot delete the workspace', function () {
    $owner = User::factory()->create();
    $admin = User::factory()->create();

    WorkspaceMembership::create([
        'workspace_id' => $owner->active_workspace_id,
        'user_id' => $admin->id,
        'role' => WorkspaceMembership::ROLE_ADMIN,
        'last_accessed_at' => now(),
    ]);
    $admin->forceFill(['active_workspace_id' => $owner->active_workspace_id])->save();
    makeSecondaryWorkspace($admin);

    $workspaceName = $owner->activeWorkspace->name;
    $workspaceId = $owner->active_workspace_id;

    $this->actingAs($admin);

    Livewire::test('pages::settings.workspace')
        ->set('deleteConfirmation', $workspaceName)
        ->call('deleteWorkspace')
        ->assertStatus(403);

    expect(Workspace::find($workspaceId))->not->toBeNull();
});

test('owner with only one workspace cannot delete it', function () {
    $owner = User::factory()->create();
    $workspaceId = $owner->active_workspace_id;
    $workspaceName = $owner->activeWorkspace->name;

    $this->actingAs($owner);

    Livewire::test('pages::settings.workspace')
        ->set('deleteConfirmation', $workspaceName)
        ->call('deleteWorkspace');

    expect(Workspace::find($workspaceId))->not->toBeNull();
});

test('owner can delete a workspace and cascades fire; active flips to MRU', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();

    $doomedId = $owner->active_workspace_id;
    $doomedName = $owner->activeWorkspace->name;

    WorkspaceMembership::create([
        'workspace_id' => $doomedId,
        'user_id' => $member->id,
        'role' => WorkspaceMembership::ROLE_MEMBER,
        'last_accessed_at' => now(),
    ]);

    Project::create([
        'workspace_id' => $doomedId,
        'name' => 'Doomed Project',
        'rigor_level' => 2,
    ]);

    WorkspaceInvitation::create([
        'workspace_id' => $doomedId,
        'email' => 'pending@example.com',
        'role' => WorkspaceMembership::ROLE_MEMBER,
        'token' => WorkspaceInvitation::generateToken(),
        'invited_by_user_id' => $owner->id,
        'expires_at' => WorkspaceInvitation::defaultExpiry(),
    ]);

    $older = makeSecondaryWorkspace($owner);
    WorkspaceMembership::where('user_id', $owner->id)
        ->where('workspace_id', $older->id)
        ->update(['last_accessed_at' => now()->subDays(5)]);

    $newer = makeSecondaryWorkspace($owner);
    WorkspaceMembership::where('user_id', $owner->id)
        ->where('workspace_id', $newer->id)
        ->update(['last_accessed_at' => now()->subHour()]);

    $this->actingAs($owner);

    Livewire::test('pages::settings.workspace')
        ->set('deleteConfirmation', $doomedName)
        ->call('deleteWorkspace');

    expect(Workspace::find($doomedId))->toBeNull();
    expect(WorkspaceMembership::where('workspace_id', $doomedId)->count())->toBe(0);
    expect(WorkspaceInvitation::where('workspace_id', $doomedId)->count())->toBe(0);
    expect(Project::where('workspace_id', $doomedId)->count())->toBe(0);
    expect($owner->fresh()->active_workspace_id)->toBe($newer->id);
});

test('switchWorkspace bumps last_accessed_at on the new membership', function () {
    $user = User::factory()->create();

    WorkspaceMembership::where('user_id', $user->id)
        ->update(['last_accessed_at' => now()->subDays(30)]);

    $other = makeSecondaryWorkspace($user);

    $user->switchWorkspace($other);

    $membership = WorkspaceMembership::where('user_id', $user->id)
        ->where('workspace_id', $other->id)
        ->first();

    expect($membership->last_accessed_at->diffInSeconds(now()))->toBeLessThan(5);
});

test('delete requires confirmation to match workspace name', function () {
    $owner = User::factory()->create();
    $workspaceId = $owner->active_workspace_id;
    makeSecondaryWorkspace($owner);

    $this->actingAs($owner);

    Livewire::test('pages::settings.workspace')
        ->set('deleteConfirmation', 'WRONG')
        ->call('deleteWorkspace');

    expect(Workspace::find($workspaceId))->not->toBeNull();
});
