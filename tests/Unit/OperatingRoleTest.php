<?php

declare(strict_types=1);

use App\Mcp\Servers\AllServer;
use App\Mcp\Servers\IntakeServer;
use App\Mcp\Servers\VerificationServer;
use App\Support\OperatingRole;
use App\Support\ViewLens;

it('has one case per role-scoped MCP server', function (): void {
    expect(OperatingRole::cases())->toHaveCount(7);
});

it('maps each role to its MCP server', function (): void {
    expect(OperatingRole::Verification->server())->toBe(VerificationServer::class)
        ->and(OperatingRole::Intake->server())->toBe(IntakeServer::class);
});

it('projects each role onto a view lens', function (): void {
    expect(OperatingRole::Intake->lens())->toBe(ViewLens::SpecWriter)
        ->and(OperatingRole::Verification->lens())->toBe(ViewLens::SpecImplementer)
        ->and(OperatingRole::Governance->lens())->toBe(ViewLens::Reviewer)
        ->and(OperatingRole::Readonly->lens())->toBe(ViewLens::All);
});

it('resolves the role a role-scoped server stands for', function (): void {
    expect(OperatingRole::forServer(VerificationServer::class))->toBe(OperatingRole::Verification);
});

it('treats AllServer as roleless — the unbound fallback', function (): void {
    expect(OperatingRole::forServer(AllServer::class))->toBeNull();
});

it('uses MCP server path names as case values', function (): void {
    expect(OperatingRole::tryFrom('verification'))->toBe(OperatingRole::Verification)
        ->and(OperatingRole::tryFrom('nonsense'))->toBeNull();
});

it('authors a distinct, non-empty persona for every role', function (): void {
    $personas = array_map(
        fn (OperatingRole $role): string => $role->personaInstructions(),
        OperatingRole::cases(),
    );

    foreach ($personas as $persona) {
        expect(trim($persona))->not->toBe('');
    }

    expect($personas)->toHaveCount(count(array_unique($personas)));
});
