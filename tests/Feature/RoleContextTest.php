<?php

use App\Mcp\Servers\AllServer;
use App\Mcp\Servers\IntakeServer;
use App\Mcp\Servers\VerificationServer;
use App\Models\User;
use App\Support\OperatingRole;
use App\Support\RoleContext;
use Laravel\Passport\AccessToken;
use Laravel\Passport\Passport;

it('resolves the operating role from a role-bound access token', function () {
    $user = User::factory()->create();

    Passport::actingAs($user, ['mcp:use']);
    $user->withAccessToken(new AccessToken([
        'oauth_scopes' => ['mcp:use'],
        'role' => 'verification',
    ]));

    $context = app(RoleContext::class);

    expect($context->role())->toBe(OperatingRole::Verification)
        ->and($context->source())->toBe('token');
});

it('leaves the session unbound when the token carries no role', function () {
    $user = User::factory()->create();

    Passport::actingAs($user, ['mcp:use']);

    $context = app(RoleContext::class);

    expect($context->role())->toBeNull()
        ->and($context->source())->toBeNull();
});

it('resolves the operating role from the GROWTH_ROLE env for a local session', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $_SERVER['GROWTH_ROLE'] = 'intake';

    try {
        $context = app(RoleContext::class);

        expect($context->role())->toBe(OperatingRole::Intake)
            ->and($context->source())->toBe('env');
    } finally {
        unset($_SERVER['GROWTH_ROLE']);
    }
});

it('ignores the GROWTH_ROLE env when no user is authenticated', function () {
    $_SERVER['GROWTH_ROLE'] = 'intake';

    try {
        expect(app(RoleContext::class)->role())->toBeNull();
    } finally {
        unset($_SERVER['GROWTH_ROLE']);
    }
});

it('requireRole throws when the session is unbound', function () {
    expect(fn () => app(RoleContext::class)->requireRole())
        ->toThrow(RuntimeException::class);
});

it('accepts a server that matches the bound role', function () {
    $context = app(RoleContext::class);
    $context->set(OperatingRole::Verification);

    expect(fn () => $context->assertServerMatches(VerificationServer::class))
        ->not->toThrow(RuntimeException::class);
});

it('fails loudly when the bound role does not match the role server', function () {
    $context = app(RoleContext::class);
    $context->set(OperatingRole::Verification);

    expect(fn () => $context->assertServerMatches(IntakeServer::class))
        ->toThrow(RuntimeException::class);
});

it('accepts the roleless AllServer for any bound role', function () {
    $context = app(RoleContext::class);
    $context->set(OperatingRole::Verification);

    expect(fn () => $context->assertServerMatches(AllServer::class))
        ->not->toThrow(RuntimeException::class);
});

it('accepts any role server for an unbound session', function () {
    expect(fn () => app(RoleContext::class)->assertServerMatches(VerificationServer::class))
        ->not->toThrow(RuntimeException::class);
});
