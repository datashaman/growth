<?php

use App\Models\User;
use App\Models\WorkspaceMembership;
use Livewire\Livewire;

test('user can create a new workspace and is switched to it', function () {
    $user = User::factory()->create();
    $originalWorkspaceId = $user->active_workspace_id;

    $this->actingAs($user);

    Livewire::test('pages::settings.workspace')
        ->set('newWorkspaceName', 'Skunkworks')
        ->call('createWorkspace');

    $user->refresh();
    expect($user->active_workspace_id)->not->toBe($originalWorkspaceId);
    expect($user->activeWorkspace->name)->toBe('Skunkworks');
    expect(WorkspaceMembership::where('user_id', $user->id)
        ->where('workspace_id', $user->active_workspace_id)
        ->value('role'))->toBe(WorkspaceMembership::ROLE_OWNER);
});

test('create workspace requires a name', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire::test('pages::settings.workspace')
        ->set('newWorkspaceName', '')
        ->call('createWorkspace')
        ->assertHasErrors('newWorkspaceName');
});

test('settings nav link is always visible regardless of workspace count', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('profile.edit'))
        ->assertOk()
        ->assertSee(route('workspace.edit'));
});
