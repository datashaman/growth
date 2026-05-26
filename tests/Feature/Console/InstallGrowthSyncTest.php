<?php

use App\Models\Project;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Laravel\Passport\Client;

beforeEach(function () {
    Client::factory()->asPersonalAccessTokenClient()->create([
        'name' => 'Growth Personal Access Client',
        'provider' => 'users',
    ]);
});

it('installs the growth sync token secret and url variable without printing the token', function () {
    $user = User::factory()->create(['email' => 'alice@example.com']);
    $project = Project::create([
        'workspace_id' => $user->active_workspace_id,
        'name' => 'Growth',
        'rigor_level' => 2,
        'github_repo' => 'datashaman/growth',
    ]);

    $keypair = sodium_crypto_box_keypair();
    $publicKey = sodium_crypto_box_publickey($keypair);

    Http::fake([
        'api.github.com/repos/datashaman/growth/actions/secrets/public-key' => Http::response([
            'key_id' => 'kid_123',
            'key' => base64_encode($publicKey),
        ]),
        'api.github.com/repos/datashaman/growth/actions/secrets/GROWTH_MCP_TOKEN' => Http::response(null, 204),
        'api.github.com/repos/datashaman/growth/actions/variables' => Http::response(null, 201),
    ]);

    $this->artisan('growth-sync:install', [
        'project' => $project->id,
        'email' => 'alice@example.com',
        '--growth-url' => 'https://growth.example.test/',
        '--github-token' => 'github-token',
    ])
        ->expectsOutputToContain('growth-sync secret and URL variable installed for datashaman/growth')
        ->doesntExpectOutputToContain('ey')
        ->assertExitCode(0);

    $token = $user->fresh()->tokens()->first();

    expect($token)
        ->not->toBeNull()
        ->and($token->workspace_id)->toBe($project->workspace_id)
        ->and($token->name)->toBe('growth-sync:datashaman/growth');

    Http::assertSent(function (Request $request) use ($keypair) {
        if ($request->url() !== 'https://api.github.com/repos/datashaman/growth/actions/secrets/GROWTH_MCP_TOKEN') {
            return false;
        }

        $body = $request->data();
        $decrypted = sodium_crypto_box_seal_open(base64_decode($body['encrypted_value']), $keypair);

        return $request->method() === 'PUT'
            && $body['key_id'] === 'kid_123'
            && $decrypted !== false
            && preg_match('/^[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+$/', $decrypted) === 1
            && ! str_contains($body['encrypted_value'], $decrypted);
    });

    Http::assertSent(fn (Request $request) => $request->url() === 'https://api.github.com/repos/datashaman/growth/actions/variables'
        && $request->method() === 'POST'
        && $request->data() === [
            'name' => 'GROWTH_URL',
            'value' => 'https://growth.example.test',
        ]);
});

it('updates the growth url variable when it already exists', function () {
    $user = User::factory()->create(['email' => 'alice@example.com']);
    $project = Project::create([
        'workspace_id' => $user->active_workspace_id,
        'name' => 'Growth',
        'rigor_level' => 2,
        'github_repo' => 'datashaman/growth',
    ]);

    $keypair = sodium_crypto_box_keypair();

    Http::fake([
        'api.github.com/repos/datashaman/growth/actions/secrets/public-key' => Http::response([
            'key_id' => 'kid_123',
            'key' => base64_encode(sodium_crypto_box_publickey($keypair)),
        ]),
        'api.github.com/repos/datashaman/growth/actions/secrets/GROWTH_MCP_TOKEN' => Http::response(null, 204),
        'api.github.com/repos/datashaman/growth/actions/variables' => Http::response(['message' => 'Already exists'], 409),
        'api.github.com/repos/datashaman/growth/actions/variables/GROWTH_URL' => Http::response(null, 204),
    ]);

    $this->artisan('growth-sync:install', [
        'project' => $project->id,
        'email' => 'alice@example.com',
        '--growth-url' => 'https://growth.example.test',
        '--github-token' => 'github-token',
    ])->assertExitCode(0);

    Http::assertSent(fn (Request $request) => $request->url() === 'https://api.github.com/repos/datashaman/growth/actions/variables/GROWTH_URL'
        && $request->method() === 'PATCH'
        && $request->data() === [
            'name' => 'GROWTH_URL',
            'value' => 'https://growth.example.test',
        ]);
});

it('rejects a user outside the project workspace', function () {
    $owner = User::factory()->create();
    $outsider = User::factory()->create(['email' => 'outside@example.com']);
    $project = Project::create([
        'workspace_id' => $owner->active_workspace_id,
        'name' => 'Growth',
        'rigor_level' => 2,
        'github_repo' => 'datashaman/growth',
    ]);

    Http::fake();

    $this->artisan('growth-sync:install', [
        'project' => $project->id,
        'email' => 'outside@example.com',
        '--growth-url' => 'https://growth.example.test',
        '--github-token' => 'github-token',
    ])
        ->expectsOutputToContain("does not belong to the project's workspace")
        ->assertExitCode(1);

    expect($outsider->fresh()->tokens()->count())->toBe(0);
    Http::assertNothingSent();
});
