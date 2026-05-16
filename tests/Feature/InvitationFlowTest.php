<?php

use App\Mail\WorkspaceInvitationMail;
use App\Models\User;
use App\Models\WorkspaceInvitation;
use App\Models\WorkspaceMembership;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

test('invitation page renders signup form for unauthenticated user', function () {
    $owner = User::factory()->create();
    $invitation = WorkspaceInvitation::factory()->forWorkspace($owner->activeWorkspace)->create([
        'email' => 'newcomer@example.test',
        'invited_by_user_id' => $owner->id,
    ]);

    $this->get(route('invitations.show', ['token' => $invitation->token]))
        ->assertOk()
        ->assertSee('newcomer@example.test')
        ->assertSee('Create account & accept');
});

test('invitation page prompts existing user to log in instead of signing up', function () {
    $owner = User::factory()->create();
    User::factory()->create(['email' => 'returning@example.test']);

    $invitation = WorkspaceInvitation::factory()->forWorkspace($owner->activeWorkspace)->create([
        'email' => 'returning@example.test',
        'invited_by_user_id' => $owner->id,
    ]);

    $this->get(route('invitations.show', ['token' => $invitation->token]))
        ->assertOk()
        ->assertSee('Log in to accept')
        ->assertDontSee('Create account & accept');
});

test('invitation page sets intended url so login returns to the invitation', function () {
    $owner = User::factory()->create();
    $invitation = WorkspaceInvitation::factory()->forWorkspace($owner->activeWorkspace)->create([
        'email' => 'newcomer@example.test',
        'invited_by_user_id' => $owner->id,
    ]);

    $url = route('invitations.show', ['token' => $invitation->token]);

    $this->get($url)->assertOk();

    expect(session('url.intended'))->toBe($url);
});

test('expired invitation is rejected', function () {
    $owner = User::factory()->create();
    $invitation = WorkspaceInvitation::factory()->expired()->forWorkspace($owner->activeWorkspace)->create([
        'invited_by_user_id' => $owner->id,
    ]);

    $this->get(route('invitations.show', ['token' => $invitation->token]))
        ->assertStatus(410);
});

test('already-accepted invitation is rejected', function () {
    $owner = User::factory()->create();
    $invitation = WorkspaceInvitation::factory()->accepted()->forWorkspace($owner->activeWorkspace)->create([
        'invited_by_user_id' => $owner->id,
    ]);

    $this->get(route('invitations.show', ['token' => $invitation->token]))
        ->assertStatus(410);
});

test('unknown token is rejected', function () {
    $this->get(route('invitations.show', ['token' => 'nope']))
        ->assertNotFound();
});

test('unauthenticated invitee can sign up and is joined to the invited workspace only', function () {
    $owner = User::factory()->create();
    $invitation = WorkspaceInvitation::factory()->forWorkspace($owner->activeWorkspace)->create([
        'email' => 'newcomer@example.test',
        'role' => WorkspaceMembership::ROLE_MEMBER,
        'invited_by_user_id' => $owner->id,
    ]);

    Livewire::test('pages::invitations.show', ['token' => $invitation->token])
        ->set('name', 'Newcomer')
        ->set('password', 'Password!1')
        ->set('passwordConfirmation', 'Password!1')
        ->call('signupAndAccept')
        ->assertRedirect('/dashboard');

    $user = User::where('email', 'newcomer@example.test')->firstOrFail();
    expect($user->active_workspace_id)->toBe($owner->active_workspace_id);
    expect($user->workspaces()->count())->toBe(1);
    expect($invitation->fresh()->accepted_at)->not->toBeNull();
});

test('authenticated user with matching email accepts inline', function () {
    $owner = User::factory()->create();
    $invitee = User::factory()->create(['email' => 'invitee@example.test']);
    $invitation = WorkspaceInvitation::factory()->forWorkspace($owner->activeWorkspace)->create([
        'email' => 'invitee@example.test',
        'role' => WorkspaceMembership::ROLE_ADMIN,
        'invited_by_user_id' => $owner->id,
    ]);

    $this->actingAs($invitee)
        ->get(route('invitations.show', ['token' => $invitation->token]))
        ->assertRedirect('/dashboard');

    expect(WorkspaceMembership::where('workspace_id', $owner->active_workspace_id)
        ->where('user_id', $invitee->id)
        ->value('role'))->toBe(WorkspaceMembership::ROLE_ADMIN);
    expect($invitee->fresh()->active_workspace_id)->toBe($owner->active_workspace_id);
    expect($invitation->fresh()->accepted_at)->not->toBeNull();
});

test('authenticated user with mismatched email gets 403', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create(['email' => 'intruder@example.test']);
    $invitation = WorkspaceInvitation::factory()->forWorkspace($owner->activeWorkspace)->create([
        'email' => 'invitee@example.test',
        'invited_by_user_id' => $owner->id,
    ]);

    $this->actingAs($intruder)
        ->get(route('invitations.show', ['token' => $invitation->token]))
        ->assertStatus(403);
});

test('invitation email contains the accept url', function () {
    Mail::fake();

    $owner = User::factory()->create();
    $invitation = WorkspaceInvitation::factory()->forWorkspace($owner->activeWorkspace)->create([
        'email' => 'newcomer@example.test',
        'invited_by_user_id' => $owner->id,
    ]);

    Mail::to($invitation->email)->send(new WorkspaceInvitationMail($invitation));

    Mail::assertQueued(WorkspaceInvitationMail::class, function (WorkspaceInvitationMail $mail) use ($invitation) {
        $rendered = $mail->render();

        return str_contains($rendered, route('invitations.show', ['token' => $invitation->token]));
    });
});
