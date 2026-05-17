<?php

use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMembership;
use Illuminate\Support\Str;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;
use Laravel\Passport\Token;

const OAUTH_REDIRECT_URI = 'https://client.test/callback';

/**
 * Register a second workspace the given user belongs to, distinct from their
 * auto-created personal workspace.
 */
function joinSecondWorkspace(User $user, string $name = 'Shared Workspace'): Workspace
{
    $workspace = Workspace::create([
        'name' => $name,
        'slug' => Workspace::uniqueSlug($name),
        'owner_user_id' => $user->id,
    ]);

    WorkspaceMembership::create([
        'workspace_id' => $workspace->id,
        'user_id' => $user->id,
        'role' => WorkspaceMembership::ROLE_OWNER,
    ]);

    return $workspace;
}

function authorizationCodeClient(): string
{
    return app(ClientRepository::class)->createAuthorizationCodeGrantClient(
        'Test MCP Client',
        [OAUTH_REDIRECT_URI],
        confidential: false,
    )->id;
}

/**
 * Drive the full PKCE authorization-code flow end to end and return the JSON
 * token response. `$workspaceId` is what gets submitted on the consent screen;
 * pass null to submit no selection at all.
 *
 * @return array<string, mixed>
 */
function completeOAuthFlow(User $user, string $clientId, ?string $workspaceId): array
{
    $verifier = Str::random(64);
    $challenge = strtr(rtrim(base64_encode(hash('sha256', $verifier, true)), '='), '+/', '-_');

    $authorize = test()->actingAs($user)->get('/oauth/authorize?'.http_build_query([
        'client_id' => $clientId,
        'redirect_uri' => OAUTH_REDIRECT_URI,
        'response_type' => 'code',
        'scope' => 'mcp:use',
        'state' => 'state-value',
        'code_challenge' => $challenge,
        'code_challenge_method' => 'S256',
    ]));
    $authorize->assertOk();

    expect(preg_match('/name="auth_token" value="([^"]+)"/', $authorize->getContent(), $matches))->toBe(1);

    $approve = test()->actingAs($user)->post('/oauth/authorize', array_filter([
        'state' => 'state-value',
        'client_id' => $clientId,
        'auth_token' => $matches[1],
        'workspace_id' => $workspaceId,
    ], fn ($value) => $value !== null));
    $approve->assertRedirect();

    parse_str((string) parse_url((string) $approve->headers->get('Location'), PHP_URL_QUERY), $callback);

    $token = test()->post('/oauth/token', [
        'grant_type' => 'authorization_code',
        'client_id' => $clientId,
        'redirect_uri' => OAUTH_REDIRECT_URI,
        'code_verifier' => $verifier,
        'code' => $callback['code'],
    ]);
    $token->assertOk();

    return $token->json();
}

it('binds an OAuth access token to the workspace chosen on the consent screen', function () {
    $user = User::factory()->create();
    $shared = joinSecondWorkspace($user);

    // A correct binding can only come from the consent choice — the chosen
    // workspace deliberately differs from the user's active workspace.
    expect($shared->id)->not->toBe($user->active_workspace_id);

    completeOAuthFlow($user, authorizationCodeClient(), $shared->id);

    $accessToken = Token::query()->where('user_id', $user->id)->sole();
    expect($accessToken->workspace_id)->toBe($shared->id);

    $refreshToken = Passport::refreshToken()->newQuery()
        ->where('access_token_id', $accessToken->id)
        ->sole();
    expect($refreshToken->workspace_id)->toBe($shared->id);
});

it('preserves the workspace binding when the token is refreshed', function () {
    $user = User::factory()->create();
    $shared = joinSecondWorkspace($user);
    $clientId = authorizationCodeClient();

    $tokens = completeOAuthFlow($user, $clientId, $shared->id);

    $refreshed = test()->post('/oauth/token', [
        'grant_type' => 'refresh_token',
        'client_id' => $clientId,
        'refresh_token' => $tokens['refresh_token'],
        'scope' => 'mcp:use',
    ]);
    $refreshed->assertOk();

    $reissued = Token::query()
        ->where('user_id', $user->id)
        ->where('revoked', false)
        ->sole();
    expect($reissued->workspace_id)->toBe($shared->id);
});

it('leaves the token unbound when the chosen workspace is not the user\'s', function () {
    $user = User::factory()->create();
    $stranger = User::factory()->create();

    // The stranger's personal workspace is one the user has no membership in.
    completeOAuthFlow($user, authorizationCodeClient(), $stranger->active_workspace_id);

    $accessToken = Token::query()->where('user_id', $user->id)->sole();
    expect($accessToken->workspace_id)->toBeNull();
});

it('lists the user\'s workspaces on the OAuth consent screen', function () {
    $user = User::factory()->create();
    joinSecondWorkspace($user);

    $verifier = Str::random(64);
    $challenge = strtr(rtrim(base64_encode(hash('sha256', $verifier, true)), '='), '+/', '-_');

    $this->actingAs($user)->get('/oauth/authorize?'.http_build_query([
        'client_id' => authorizationCodeClient(),
        'redirect_uri' => OAUTH_REDIRECT_URI,
        'response_type' => 'code',
        'scope' => 'mcp:use',
        'state' => 'state-value',
        'code_challenge' => $challenge,
        'code_challenge_method' => 'S256',
    ]))
        ->assertOk()
        ->assertSee('name="workspace_id"', false)
        ->assertSee('Shared Workspace')
        ->assertSee('Personal');
});

it('submits a hidden workspace field for a single-workspace user', function () {
    $user = User::factory()->create();

    $verifier = Str::random(64);
    $challenge = strtr(rtrim(base64_encode(hash('sha256', $verifier, true)), '='), '+/', '-_');

    $this->actingAs($user)->get('/oauth/authorize?'.http_build_query([
        'client_id' => authorizationCodeClient(),
        'redirect_uri' => OAUTH_REDIRECT_URI,
        'response_type' => 'code',
        'scope' => 'mcp:use',
        'state' => 'state-value',
        'code_challenge' => $challenge,
        'code_challenge_method' => 'S256',
    ]))
        ->assertOk()
        ->assertSee('type="hidden" name="workspace_id"', false)
        ->assertDontSee('<select', false);
});
