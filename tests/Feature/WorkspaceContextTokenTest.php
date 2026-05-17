<?php

use App\Models\User;
use App\Models\Workspace;
use App\Support\WorkspaceContext;
use Laravel\Passport\AccessToken;
use Laravel\Passport\Passport;

it('resolves the active workspace from a workspace-bound access token', function () {
    $user = User::factory()->create();
    $bound = Workspace::create([
        'name' => 'Bound Workspace',
        'slug' => Workspace::uniqueSlug('bound-workspace'),
        'owner_user_id' => $user->id,
    ]);

    expect($bound->id)->not->toBe($user->active_workspace_id);

    Passport::actingAs($user, ['mcp:use']);
    $user->withAccessToken(new AccessToken([
        'oauth_scopes' => ['mcp:use'],
        'workspace_id' => $bound->id,
    ]));

    // The bound workspace differs from the user's active workspace, so a
    // correct resolution can only come from the token path.
    expect(app(WorkspaceContext::class)->id())->toBe($bound->id);
});

it('falls through to the user workspace when the token carries no binding', function () {
    $user = User::factory()->create();

    Passport::actingAs($user, ['mcp:use']);

    expect(app(WorkspaceContext::class)->id())->toBe($user->active_workspace_id);
});
