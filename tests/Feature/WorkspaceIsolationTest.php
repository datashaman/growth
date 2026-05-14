<?php

use App\Models\Project;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMembership;
use App\Support\WorkspaceContext;

it('creates a personal workspace when a user is first created', function () {
    $user = User::factory()->create(['name' => 'Alice', 'email' => 'alice@example.com']);

    expect($user->active_workspace_id)->not->toBeNull()
        ->and($user->personalWorkspace)->not->toBeNull()
        ->and($user->personalWorkspace->owner_user_id)->toBe($user->id);

    $membership = WorkspaceMembership::where('workspace_id', $user->active_workspace_id)
        ->where('user_id', $user->id)
        ->first();

    expect($membership)->not->toBeNull()
        ->and($membership->role)->toBe(WorkspaceMembership::ROLE_OWNER);
});

it('scopes Project queries to the active workspace only', function () {
    $alice = User::factory()->create();
    $second = Workspace::create([
        'name' => 'Side Project',
        'slug' => Workspace::uniqueSlug('side-project'),
        'owner_user_id' => $alice->id,
    ]);
    WorkspaceMembership::create([
        'workspace_id' => $second->id,
        'user_id' => $alice->id,
        'role' => WorkspaceMembership::ROLE_OWNER,
    ]);

    $inPersonal = Project::create([
        'workspace_id' => $alice->active_workspace_id,
        'name' => 'Personal',
        'rigor_level' => 2,
    ]);
    $inSecond = Project::create([
        'workspace_id' => $second->id,
        'name' => 'Side',
        'rigor_level' => 2,
    ]);

    $this->actingAs($alice);

    expect(Project::pluck('id')->all())->toBe([$inPersonal->id]);

    $alice->forceFill(['active_workspace_id' => $second->id])->save();
    app(WorkspaceContext::class)->forget();

    $this->actingAs($alice->fresh());

    expect(Project::pluck('id')->all())->toBe([$inSecond->id]);
});

it('returns all projects when no user is authenticated', function () {
    $alice = User::factory()->create();
    Project::create([
        'workspace_id' => $alice->active_workspace_id,
        'name' => 'Visible without auth',
        'rigor_level' => 2,
    ]);

    expect(Project::count())->toBe(1);
});
