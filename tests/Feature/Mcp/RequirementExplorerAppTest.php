<?php

use App\Mcp\Servers\IntakeServer;
use App\Mcp\Servers\ReadonlyServer;
use App\Models\User;
use Laravel\Passport\Passport;

beforeEach(function () {
    Passport::actingAs(User::factory()->create(), ['mcp:use']);
});

it('lists the requirement-explorer app on readonly server', function () {
    $resources = test()->postJson('/mcp/readonly', [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'resources/list',
    ])->assertOk()->json('result.resources');

    $entry = collect($resources)->firstWhere('uri', 'ui://resources/requirement-explorer');
    expect($entry)->not->toBeNull()
        ->and($entry['mimeType'])->toBe('text/html;profile=mcp-app');
});

it('lists the requirement-explorer app on intake server', function () {
    $resources = test()->postJson('/mcp/intake', [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'resources/list',
    ])->assertOk()->json('result.resources');

    $entry = collect($resources)->firstWhere('uri', 'ui://resources/requirement-explorer');
    expect($entry)->not->toBeNull()
        ->and($entry['mimeType'])->toBe('text/html;profile=mcp-app');
});

it('renders the requirement-explorer blade with the expected MCP wiring', function () {
    readResource(ReadonlyServer::class, 'ui://resources/requirement-explorer')
        ->assertOk()
        ->assertSee('createMcpApp')
        ->assertSee('list-requirements')
        ->assertSee('list-projects')
        ->assertSee('trace-query')
        ->assertSee('lint-project')
        ->assertSee('Requirements')
        ->assertSee('window.GrowthApp')
        ->assertSee('state.projects = payload?.results ?? []', false)
        ->assertDontSee('state.projects = payload?.projects', false);
});

it('also serves the requirement-explorer app on the intake server', function () {
    readResource(IntakeServer::class, 'ui://resources/requirement-explorer')
        ->assertOk()
        ->assertSee('createMcpApp')
        ->assertSee('list-requirements');
});
