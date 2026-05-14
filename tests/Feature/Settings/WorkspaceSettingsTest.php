<?php

use App\Models\User;
use App\Models\WorkspaceMembership;
use Livewire\Livewire;

test('owner can rename the workspace', function () {
    $owner = User::factory()->create();

    $this->actingAs($owner);

    Livewire::test('pages::settings.workspace')
        ->set('name', 'Renamed')
        ->call('saveName')
        ->assertHasNoErrors();

    expect($owner->activeWorkspace->fresh()->name)->toBe('Renamed');
});

test('viewer cannot rename the workspace', function () {
    $owner = User::factory()->create();
    $viewer = User::factory()->create();
    WorkspaceMembership::where('workspace_id', $viewer->active_workspace_id)
        ->where('user_id', $viewer->id)
        ->delete();
    WorkspaceMembership::create([
        'workspace_id' => $owner->active_workspace_id,
        'user_id' => $viewer->id,
        'role' => WorkspaceMembership::ROLE_VIEWER,
    ]);
    $viewer->forceFill(['active_workspace_id' => $owner->active_workspace_id])->save();

    $this->actingAs($viewer);

    Livewire::test('pages::settings.workspace')
        ->set('name', 'Hacked')
        ->call('saveName')
        ->assertStatus(403);

    expect($owner->activeWorkspace->fresh()->name)->not->toBe('Hacked');
});

test('admin can change member roles', function () {
    $owner = User::factory()->create();
    $admin = User::factory()->create();
    $member = User::factory()->create();

    WorkspaceMembership::create([
        'workspace_id' => $owner->active_workspace_id,
        'user_id' => $admin->id,
        'role' => WorkspaceMembership::ROLE_ADMIN,
    ]);
    $memberMembership = WorkspaceMembership::create([
        'workspace_id' => $owner->active_workspace_id,
        'user_id' => $member->id,
        'role' => WorkspaceMembership::ROLE_MEMBER,
    ]);

    $admin->forceFill(['active_workspace_id' => $owner->active_workspace_id])->save();
    $this->actingAs($admin);

    Livewire::test('pages::settings.workspace')
        ->call('setRole', $memberMembership->id, WorkspaceMembership::ROLE_VIEWER);

    expect($memberMembership->fresh()->role)->toBe(WorkspaceMembership::ROLE_VIEWER);
});

test('owner cannot demote themselves if they are the last owner', function () {
    $owner = User::factory()->create();
    $ownership = WorkspaceMembership::where('workspace_id', $owner->active_workspace_id)
        ->where('user_id', $owner->id)
        ->first();

    $this->actingAs($owner);

    Livewire::test('pages::settings.workspace')
        ->call('setRole', $ownership->id, WorkspaceMembership::ROLE_ADMIN);

    expect($ownership->fresh()->role)->toBe(WorkspaceMembership::ROLE_OWNER);
});

test('cannot remove the last owner', function () {
    $owner = User::factory()->create();
    $ownership = WorkspaceMembership::where('workspace_id', $owner->active_workspace_id)
        ->where('user_id', $owner->id)
        ->first();

    $this->actingAs($owner);

    Livewire::test('pages::settings.workspace')
        ->call('remove', $ownership->id);

    expect($ownership->fresh())->not->toBeNull();
});

test('admin can remove a member', function () {
    $owner = User::factory()->create();
    $admin = User::factory()->create();
    $member = User::factory()->create();

    WorkspaceMembership::create([
        'workspace_id' => $owner->active_workspace_id,
        'user_id' => $admin->id,
        'role' => WorkspaceMembership::ROLE_ADMIN,
    ]);
    $memberMembership = WorkspaceMembership::create([
        'workspace_id' => $owner->active_workspace_id,
        'user_id' => $member->id,
        'role' => WorkspaceMembership::ROLE_MEMBER,
    ]);

    $admin->forceFill(['active_workspace_id' => $owner->active_workspace_id])->save();
    $this->actingAs($admin);

    Livewire::test('pages::settings.workspace')
        ->call('remove', $memberMembership->id);

    expect(WorkspaceMembership::find($memberMembership->id))->toBeNull();
});

test('admin cannot demote the last owner', function () {
    $owner = User::factory()->create();
    $admin = User::factory()->create();

    WorkspaceMembership::create([
        'workspace_id' => $owner->active_workspace_id,
        'user_id' => $admin->id,
        'role' => WorkspaceMembership::ROLE_ADMIN,
    ]);
    $admin->forceFill(['active_workspace_id' => $owner->active_workspace_id])->save();

    $ownership = WorkspaceMembership::where('workspace_id', $owner->active_workspace_id)
        ->where('user_id', $owner->id)
        ->first();

    $this->actingAs($admin);

    Livewire::test('pages::settings.workspace')
        ->call('setRole', $ownership->id, WorkspaceMembership::ROLE_ADMIN)
        ->assertStatus(403);

    expect($ownership->fresh()->role)->toBe(WorkspaceMembership::ROLE_OWNER);
});

test('workspace settings page renders members', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    WorkspaceMembership::create([
        'workspace_id' => $owner->active_workspace_id,
        'user_id' => $member->id,
        'role' => WorkspaceMembership::ROLE_MEMBER,
    ]);

    $this->actingAs($owner)
        ->get(route('workspace.edit'))
        ->assertOk()
        ->assertSee($member->email);
});

test('viewer cannot change roles', function () {
    $owner = User::factory()->create();
    $viewer = User::factory()->create();
    WorkspaceMembership::create([
        'workspace_id' => $owner->active_workspace_id,
        'user_id' => $viewer->id,
        'role' => WorkspaceMembership::ROLE_VIEWER,
    ]);
    $viewer->forceFill(['active_workspace_id' => $owner->active_workspace_id])->save();

    $ownership = WorkspaceMembership::where('workspace_id', $owner->active_workspace_id)
        ->where('user_id', $owner->id)
        ->first();

    $this->actingAs($viewer);

    Livewire::test('pages::settings.workspace')
        ->call('setRole', $ownership->id, WorkspaceMembership::ROLE_VIEWER)
        ->assertStatus(403);
});
