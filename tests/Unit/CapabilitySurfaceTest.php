<?php

declare(strict_types=1);

use App\Mcp\Servers\AllServer;
use App\Mcp\Servers\IntakeServer;
use App\Mcp\Servers\VerificationServer;
use App\Support\CapabilitySurface;

it('has one case per role-scoped MCP server', function (): void {
    expect(CapabilitySurface::cases())->toHaveCount(7);
});

it('maps each surface to its MCP server', function (): void {
    expect(CapabilitySurface::Verification->server())->toBe(VerificationServer::class)
        ->and(CapabilitySurface::Intake->server())->toBe(IntakeServer::class);
});

it('resolves the surface a role-scoped server stands for', function (): void {
    expect(CapabilitySurface::forServer(VerificationServer::class))->toBe(CapabilitySurface::Verification);
});

it('treats AllServer as roleless — the unbound fallback', function (): void {
    expect(CapabilitySurface::forServer(AllServer::class))->toBeNull();
});

it('uses MCP server path names as case values', function (): void {
    expect(CapabilitySurface::tryFrom('verification'))->toBe(CapabilitySurface::Verification)
        ->and(CapabilitySurface::tryFrom('nonsense'))->toBeNull();
});
