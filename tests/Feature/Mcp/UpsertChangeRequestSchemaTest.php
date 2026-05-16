<?php

use App\Models\ChangeImpact;
use App\Models\User;
use Laravel\Passport\Passport;

it('exposes impact_kind enum values in the upsert-change-request tool schema', function () {
    Passport::actingAs(User::factory()->create(), ['mcp:use']);

    $tools = $this->postJson('/mcp/governance', [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/list',
    ])->assertOk()->json('result.tools');

    $changeTool = collect($tools)->firstWhere('name', 'upsert-change-request');

    expect($changeTool)->not->toBeNull();

    $impactKindSchema = $changeTool['inputSchema']['properties']['impacts']['items']['properties']['impact_kind'] ?? null;

    expect($impactKindSchema)->not->toBeNull()
        ->and($impactKindSchema['enum'] ?? null)->toBe(ChangeImpact::KINDS);
});

it('omits transition-managed fields from the upsert-change-request tool schema', function () {
    Passport::actingAs(User::factory()->create(), ['mcp:use']);

    $tools = $this->postJson('/mcp/governance', [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/list',
    ])->assertOk()->json('result.tools');

    $changeTool = collect($tools)->firstWhere('name', 'upsert-change-request');

    expect($changeTool)->not->toBeNull();

    $properties = $changeTool['inputSchema']['properties'] ?? [];

    expect($properties)->not->toHaveKey('status')
        ->and($properties)->not->toHaveKey('decision')
        ->and($properties)->not->toHaveKey('decision_rationale')
        ->and($properties)->not->toHaveKey('decided_at');
});
