<?php

use App\Mcp\Servers\IntakeServer;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Laravel\Mcp\Server\Transport\FakeTransporter;

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

it('produces a token that the local auth path will accept', function () {
    $user = User::factory()->create(['email' => 'alice@example.com']);

    // Capture the printed token. The command prints it on its own line.
    Artisan::call('user:token', ['email' => 'alice@example.com']);
    $output = Artisan::output();

    preg_match('/^\d+\|[A-Za-z0-9]+$/m', $output, $matches);
    $token = $matches[0] ?? null;
    expect($token)->not->toBeNull();

    $server = app(IntakeServer::class, ['transport' => new FakeTransporter]);
    expect($server->authenticateLocalSession($token))->toBe($user->id);
});
