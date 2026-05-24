<?php

use App\Support\DesignTokenResolver;

it('returns all four tokens with base defaults when no context is given', function (): void {
    $result = app(DesignTokenResolver::class)->resolve([]);

    expect($result)->toHaveKeys(['surface', 'elevation', 'radius', 'spacing_inner']);
    expect($result['surface'])->toBe(['value' => 'default', 'reason' => 'No context matched; base default applied.', 'source' => 'base:default']);
    expect($result['elevation']['source'])->toBe('base:default');
});

it('density:compact overrides spacing_inner and radius but not surface', function (): void {
    $result = app(DesignTokenResolver::class)->resolve(['density' => 'compact']);

    expect($result['spacing_inner']['value'])->toBe('tight');
    expect($result['spacing_inner']['source'])->toBe('density:compact');
    expect($result['radius']['value'])->toBe('tight');
    expect($result['surface']['source'])->toBe('base:default');
});

it('state wins over surface which wins over density for the same token', function (): void {
    $result = app(DesignTokenResolver::class)->resolve([
        'density' => 'compact',
        'surface' => 'form',
        'state' => 'disabled',
    ]);

    expect($result['surface']['source'])->toBe('state:disabled');
    expect($result['elevation']['source'])->toBe('state:disabled');
    expect($result['radius']['source'])->toBe('density:compact');
    expect($result['spacing_inner']['source'])->toBe('density:compact');
});

it('reason strings mention the winning layer', function (): void {
    $result = app(DesignTokenResolver::class)->resolve(['component' => 'button']);

    expect($result['radius']['reason'])->toContain('component');
    expect($result['surface']['reason'])->toContain('base');
});

it('unknown context values are ignored and base defaults apply', function (): void {
    $result = app(DesignTokenResolver::class)->resolve(['state' => 'nonexistent']);

    foreach (DesignTokenResolver::TOKENS as $token) {
        expect($result[$token]['source'])->toBe('base:default');
    }
});

it('component and state produce correct cross-layer precedence', function (): void {
    $result = app(DesignTokenResolver::class)->resolve([
        'component' => 'button',
        'state' => 'hover',
    ]);

    expect($result['elevation']['source'])->toBe('state:hover');
    expect($result['radius']['source'])->toBe('component:button');
    expect($result['surface']['source'])->toBe('base:default');
});

it('mode:dark overrides surface before other layers apply', function (): void {
    $result = app(DesignTokenResolver::class)->resolve(['mode' => 'dark']);

    expect($result['surface']['value'])->toBe('muted-dark');
    expect($result['surface']['source'])->toBe('mode:dark');
});

it('surface:form overrides mode:dark on surface because surface wins over mode', function (): void {
    $result = app(DesignTokenResolver::class)->resolve([
        'mode' => 'dark',
        'surface' => 'form',
    ]);

    expect($result['surface']['source'])->toBe('surface:form');
    expect($result['surface']['value'])->toBe('muted');
});
