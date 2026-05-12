<?php

use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Laravel\Passport\Client;

beforeEach(function () {
    Client::factory()->asPersonalAccessTokenClient()->create([
        'name' => 'Growth Personal Access Client',
        'provider' => 'users',
    ]);
});

it('issues a token for an existing user', function () {
    $user = User::factory()->create(['email' => 'alice@example.com']);

    $this->artisan('user:token', ['email' => 'alice@example.com'])
        ->expectsOutputToContain('Token issued for alice@example.com')
        ->assertExitCode(0);

    expect($user->fresh()->tokens()->count())->toBe(1);
});

it('issues a token labeled by --name', function () {
    User::factory()->create(['email' => 'alice@example.com']);

    $this->artisan('user:token', [
        'email' => 'alice@example.com',
        '--name' => 'ci-bot',
    ])->expectsOutputToContain('label: ci-bot')
        ->assertExitCode(0);
});

it('fails for an unknown email', function () {
    $this->artisan('user:token', ['email' => 'nobody@example.com'])
        ->expectsOutputToContain('No user found')
        ->assertExitCode(1);
});

it('produces a Passport access token', function () {
    User::factory()->create(['email' => 'alice@example.com']);

    Artisan::call('user:token', ['email' => 'alice@example.com']);
    $output = Artisan::output();

    preg_match('/^[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+$/m', $output, $matches);
    $token = $matches[0] ?? null;
    expect($token)->not->toBeNull();
});
