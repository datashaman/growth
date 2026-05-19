<?php

use App\Mcp\Servers\AllServer;
use App\Mcp\Servers\IntakeServer;
use App\Mcp\Servers\VerificationServer;
use App\Models\User;
use App\Support\CapabilitySurface;
use App\Support\SurfaceContext;
use Laravel\Passport\AccessToken;
use Laravel\Passport\Passport;

it('resolves the capability surface from a surface-bound access token', function () {
    $user = User::factory()->create();

    Passport::actingAs($user, ['mcp:use']);
    $user->withAccessToken(new AccessToken([
        'oauth_scopes' => ['mcp:use'],
        'surface' => 'verification',
    ]));

    $context = app(SurfaceContext::class);

    expect($context->surface())->toBe(CapabilitySurface::Verification)
        ->and($context->source())->toBe('token');
});

it('leaves the session unbound when the token carries no surface', function () {
    $user = User::factory()->create();

    Passport::actingAs($user, ['mcp:use']);

    $context = app(SurfaceContext::class);

    expect($context->surface())->toBeNull()
        ->and($context->source())->toBeNull();
});

it('resolves the capability surface from the GROWTH_SURFACE env for a local session', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $_SERVER['GROWTH_SURFACE'] = 'intake';

    try {
        $context = app(SurfaceContext::class);

        expect($context->surface())->toBe(CapabilitySurface::Intake)
            ->and($context->source())->toBe('env');
    } finally {
        unset($_SERVER['GROWTH_SURFACE']);
    }
});

it('ignores the GROWTH_SURFACE env when no user is authenticated', function () {
    $_SERVER['GROWTH_SURFACE'] = 'intake';

    try {
        expect(app(SurfaceContext::class)->surface())->toBeNull();
    } finally {
        unset($_SERVER['GROWTH_SURFACE']);
    }
});

it('requireSurface throws when the session is unbound', function () {
    expect(fn () => app(SurfaceContext::class)->requireSurface())
        ->toThrow(RuntimeException::class);
});

it('accepts a server that matches the bound surface', function () {
    $context = app(SurfaceContext::class);
    $context->set(CapabilitySurface::Verification);

    expect(fn () => $context->assertServerMatches(VerificationServer::class))
        ->not->toThrow(RuntimeException::class);
});

it('fails loudly when the bound surface does not match the surface server', function () {
    $context = app(SurfaceContext::class);
    $context->set(CapabilitySurface::Verification);

    expect(fn () => $context->assertServerMatches(IntakeServer::class))
        ->toThrow(RuntimeException::class);
});

it('accepts the surfaceless AllServer for any bound surface', function () {
    $context = app(SurfaceContext::class);
    $context->set(CapabilitySurface::Verification);

    expect(fn () => $context->assertServerMatches(AllServer::class))
        ->not->toThrow(RuntimeException::class);
});

it('accepts any surface server for an unbound session', function () {
    expect(fn () => app(SurfaceContext::class)->assertServerMatches(VerificationServer::class))
        ->not->toThrow(RuntimeException::class);
});
