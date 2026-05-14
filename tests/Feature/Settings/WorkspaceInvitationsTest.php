<?php

use App\Mail\WorkspaceInvitationMail;
use App\Models\User;
use App\Models\WorkspaceInvitation;
use App\Models\WorkspaceMembership;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

test('admin can invite a new email', function () {
    Mail::fake();

    $owner = User::factory()->create();

    $this->actingAs($owner);

    Livewire::test('pages::settings.workspace')
        ->set('inviteEmail', 'invitee@example.test')
        ->set('inviteRole', WorkspaceMembership::ROLE_MEMBER)
        ->call('sendInvitation')
        ->assertHasNoErrors();

    $invitation = WorkspaceInvitation::where('email', 'invitee@example.test')->first();
    expect($invitation)->not->toBeNull();
    expect($invitation->workspace_id)->toBe($owner->active_workspace_id);
    expect($invitation->role)->toBe(WorkspaceMembership::ROLE_MEMBER);

    Mail::assertQueued(WorkspaceInvitationMail::class);
});

test('only owners can invite as owner', function () {
    $owner = User::factory()->create();
    $admin = User::factory()->create();
    WorkspaceMembership::create([
        'workspace_id' => $owner->active_workspace_id,
        'user_id' => $admin->id,
        'role' => WorkspaceMembership::ROLE_ADMIN,
    ]);
    $admin->forceFill(['active_workspace_id' => $owner->active_workspace_id])->save();

    $this->actingAs($admin);

    Livewire::test('pages::settings.workspace')
        ->set('inviteEmail', 'invitee@example.test')
        ->set('inviteRole', WorkspaceMembership::ROLE_OWNER)
        ->call('sendInvitation')
        ->assertHasErrors('inviteRole');

    expect(WorkspaceInvitation::count())->toBe(0);
});

test('viewer cannot invite at all', function () {
    $owner = User::factory()->create();
    $viewer = User::factory()->create();
    WorkspaceMembership::create([
        'workspace_id' => $owner->active_workspace_id,
        'user_id' => $viewer->id,
        'role' => WorkspaceMembership::ROLE_VIEWER,
    ]);
    $viewer->forceFill(['active_workspace_id' => $owner->active_workspace_id])->save();

    $this->actingAs($viewer);

    Livewire::test('pages::settings.workspace')
        ->set('inviteEmail', 'invitee@example.test')
        ->set('inviteRole', WorkspaceMembership::ROLE_MEMBER)
        ->call('sendInvitation')
        ->assertStatus(403);
});

test('inviting an existing member is rejected', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create(['email' => 'already@example.test']);
    WorkspaceMembership::create([
        'workspace_id' => $owner->active_workspace_id,
        'user_id' => $member->id,
        'role' => WorkspaceMembership::ROLE_MEMBER,
    ]);

    $this->actingAs($owner);

    Livewire::test('pages::settings.workspace')
        ->set('inviteEmail', 'already@example.test')
        ->set('inviteRole', WorkspaceMembership::ROLE_MEMBER)
        ->call('sendInvitation')
        ->assertHasErrors('inviteEmail');

    expect(WorkspaceInvitation::count())->toBe(0);
});

test('re-invite replaces token and bumps expiry instead of creating a duplicate', function () {
    Mail::fake();

    $owner = User::factory()->create();
    $existing = WorkspaceInvitation::factory()->forWorkspace($owner->activeWorkspace)->create([
        'email' => 'invitee@example.test',
        'invited_by_user_id' => $owner->id,
        'expires_at' => now()->addDays(2),
    ]);
    $oldToken = $existing->token;

    $this->actingAs($owner);

    Livewire::test('pages::settings.workspace')
        ->set('inviteEmail', 'invitee@example.test')
        ->set('inviteRole', WorkspaceMembership::ROLE_ADMIN)
        ->call('sendInvitation')
        ->assertHasNoErrors();

    expect(WorkspaceInvitation::where('email', 'invitee@example.test')->count())->toBe(1);
    $refreshed = $existing->fresh();
    expect($refreshed->token)->not->toBe($oldToken);
    expect($refreshed->role)->toBe(WorkspaceMembership::ROLE_ADMIN);
    expect($refreshed->expires_at->greaterThan(now()->addDays(2)))->toBeTrue();
});

test('admin can cancel a pending invitation', function () {
    $owner = User::factory()->create();
    $invitation = WorkspaceInvitation::factory()->forWorkspace($owner->activeWorkspace)->create([
        'invited_by_user_id' => $owner->id,
    ]);

    $this->actingAs($owner);

    Livewire::test('pages::settings.workspace')
        ->call('cancelInvitation', $invitation->id);

    expect(WorkspaceInvitation::find($invitation->id))->toBeNull();
});

test('admin can resend a pending invitation; resend rotates the token', function () {
    Mail::fake();

    $owner = User::factory()->create();
    $invitation = WorkspaceInvitation::factory()->forWorkspace($owner->activeWorkspace)->create([
        'invited_by_user_id' => $owner->id,
        'expires_at' => now()->addDays(2),
    ]);
    $oldToken = $invitation->token;

    $this->actingAs($owner);

    Livewire::test('pages::settings.workspace')
        ->call('resendInvitation', $invitation->id);

    Mail::assertQueued(WorkspaceInvitationMail::class);
    $refreshed = $invitation->fresh();
    expect($refreshed->token)->not->toBe($oldToken);
    expect($refreshed->expires_at->greaterThan(now()->addDays(2)))->toBeTrue();
});

test('settings page lists pending invitations', function () {
    $owner = User::factory()->create();
    $invitation = WorkspaceInvitation::factory()->forWorkspace($owner->activeWorkspace)->create([
        'email' => 'pending@example.test',
        'invited_by_user_id' => $owner->id,
    ]);

    $this->actingAs($owner)
        ->get(route('workspace.edit'))
        ->assertOk()
        ->assertSee('pending@example.test');
});
