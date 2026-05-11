<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('creates a user with a default name and random password', function () {
    $this->artisan('user:create', ['email' => 'alice@example.com'])
        ->expectsOutputToContain('User created for alice@example.com')
        ->expectsOutputToContain("GROWTH_USER_EMAIL='alice@example.com'")
        ->assertExitCode(0);

    $user = User::where('email', 'alice@example.com')->first();

    expect($user)->not->toBeNull()
        ->and($user->name)->toBe('alice')
        ->and($user->password)->not->toBeNull();
});

it('creates a user with explicit name and password', function () {
    $this->artisan('user:create', [
        'email' => 'alice@example.com',
        '--name' => 'Alice',
        '--password' => 'secret-password',
    ])->assertExitCode(0);

    $user = User::where('email', 'alice@example.com')->firstOrFail();

    expect($user->name)->toBe('Alice')
        ->and(Hash::check('secret-password', $user->password))->toBeTrue();
});

it('fails when the email already exists', function () {
    User::factory()->create(['email' => 'alice@example.com']);

    $this->artisan('user:create', ['email' => 'alice@example.com'])
        ->expectsOutputToContain('User already exists')
        ->assertExitCode(1);
});
