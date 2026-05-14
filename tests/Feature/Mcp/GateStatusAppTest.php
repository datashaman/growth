<?php

use App\Mcp\Servers\GovernanceServer;
use App\Mcp\Servers\ReadonlyServer;
use App\Models\User;
use Laravel\Passport\Passport;

beforeEach(function () {
    Passport::actingAs(User::factory()->create(), ['mcp:use']);
});

it('lists the gate-status app on readonly server', function () {
    $resources = test()->postJson('/mcp/readonly', [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'resources/list',
    ])->assertOk()->json('result.resources');

    $entry = collect($resources)->firstWhere('uri', 'ui://resources/gate-status');
    expect($entry)->not->toBeNull()
        ->and($entry['mimeType'])->toBe('text/html;profile=mcp-app');
});

it('lists the gate-status app on governance server', function () {
    $resources = test()->postJson('/mcp/governance', [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'resources/list',
    ])->assertOk()->json('result.resources');

    $entry = collect($resources)->firstWhere('uri', 'ui://resources/gate-status');
    expect($entry)->not->toBeNull()
        ->and($entry['mimeType'])->toBe('text/html;profile=mcp-app');
});

it('renders the gate-status app blade with the expected MCP wiring', function () {
    readResource(ReadonlyServer::class, 'ui://resources/gate-status')
        ->assertOk()
        ->assertSee('createMcpApp')
        ->assertSee('evaluate-readiness-gates')
        ->assertSee('list-projects')
        ->assertSee('Gate Status')
        ->assertSee('window.GrowthApp');
});

it('also serves the gate-status app on the governance server', function () {
    readResource(GovernanceServer::class, 'ui://resources/gate-status')
        ->assertOk()
        ->assertSee('createMcpApp')
        ->assertSee('evaluate-readiness-gates');
});
