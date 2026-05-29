<?php

use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMembership;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->second = Workspace::create([
        'name' => 'Side',
        'slug' => Workspace::uniqueSlug('side'),
        'owner_user_id' => $this->user->id,
    ]);
    WorkspaceMembership::create([
        'workspace_id' => $this->second->id,
        'user_id' => $this->user->id,
        'role' => WorkspaceMembership::ROLE_OWNER,
    ]);
});

test('switcher persists the new active workspace on the user', function () {
    $this->actingAs($this->user);

    Livewire::test('workspace-switcher')
        ->set('selectedWorkspaceId', $this->second->id);

    expect($this->user->fresh()->active_workspace_id)->toBe($this->second->id);
});

test('switcher redirects after switching', function () {
    $this->actingAs($this->user);

    Livewire::test('workspace-switcher')
        ->set('selectedWorkspaceId', $this->second->id)
        ->assertRedirect();
});

test('switcher rejects workspaces the user does not belong to', function () {
    $stranger = User::factory()->create();
    $strangerWorkspace = $stranger->personalWorkspace;

    $this->actingAs($this->user);

    Livewire::test('workspace-switcher')
        ->set('selectedWorkspaceId', $strangerWorkspace->id);

    expect($this->user->fresh()->active_workspace_id)->not->toBe($strangerWorkspace->id);
});

test('switcher lists workspaces alphabetically', function () {
    $alpha = Workspace::create([
        'name' => 'alpha',
        'slug' => Workspace::uniqueSlug('alpha'),
        'owner_user_id' => $this->user->id,
    ]);
    WorkspaceMembership::create([
        'workspace_id' => $alpha->id,
        'user_id' => $this->user->id,
        'role' => WorkspaceMembership::ROLE_OWNER,
    ]);

    $beta = Workspace::create([
        'name' => 'Beta',
        'slug' => Workspace::uniqueSlug('beta'),
        'owner_user_id' => $this->user->id,
    ]);
    WorkspaceMembership::create([
        'workspace_id' => $beta->id,
        'user_id' => $this->user->id,
        'role' => WorkspaceMembership::ROLE_OWNER,
    ]);

    $this->actingAs($this->user);

    Livewire::test('workspace-switcher')
        ->assertSeeInOrder(['alpha', 'Beta', 'Personal', 'Side']);
});

test('switcher clears the selected project on switch', function () {
    session(['selected_project_id' => 'some-project']);
    $this->actingAs($this->user);

    Livewire::test('workspace-switcher')
        ->set('selectedWorkspaceId', $this->second->id);

    expect(session('selected_project_id'))->toBeNull();
});
