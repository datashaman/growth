<?php

use App\Mcp\Servers\ReadonlyServer;
use App\Models\User;
use Laravel\Passport\Passport;

beforeEach(function () {
    Passport::actingAs(User::factory()->create(), ['mcp:use']);
});

it('lists the trace-graph app on readonly server', function () {
    $resources = test()->postJson('/mcp/readonly', [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'resources/list',
    ])->assertOk()->json('result.resources');

    $entry = collect($resources)->firstWhere('uri', 'ui://resources/trace-graph');
    expect($entry)->not->toBeNull()
        ->and($entry['mimeType'])->toBe('text/html;profile=mcp-app');
});

it('renders the trace-graph blade with the expected MCP wiring', function () {
    readResource(ReadonlyServer::class, 'ui://resources/trace-graph')
        ->assertOk()
        ->assertSee('createMcpApp')
        ->assertSee('list-capabilities')
        ->assertSee('list-projects')
        ->assertSee('trace-query')
        ->assertSee('Trace Graph')
        ->assertSee('window.GrowthApp')
        ->assertSee('vis-network');
});
