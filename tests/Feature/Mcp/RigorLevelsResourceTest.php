<?php

use App\Models\User;
use Laravel\Passport\Passport;

beforeEach(function () {
    Passport::actingAs(User::factory()->create(), ['mcp:use']);
});

it('serves the rigor-levels resource on every role server', function () {
    foreach (['/mcp/intake', '/mcp/planning', '/mcp/architecture', '/mcp/verification', '/mcp/governance', '/mcp/readonly', '/mcp/all'] as $endpoint) {
        $resources = $this->postJson($endpoint, [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'resources/list',
        ])->assertOk()->json('result.resources');

        expect(collect($resources)->pluck('uri')->all())
            ->toContain('growth://rigor-levels');
    }
});

it('returns a markdown table with a row for each active rigor level', function () {
    $response = $this->postJson('/mcp/readonly', [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'resources/read',
        'params' => ['uri' => 'growth://rigor-levels'],
    ])->assertOk()->json('result.contents');

    $body = collect($response)->firstWhere('uri', 'growth://rigor-levels')['text'] ?? null;

    expect($body)->not->toBeNull()
        ->and($body)->toContain('| 1 ')
        ->and($body)->toContain('| 2 ')
        ->and($body)->toContain('| 3 ')
        ->and($body)->toContain('| 4 ')
        ->and($body)->toContain('milestone')
        ->and($body)->toContain('work item')
        ->and($body)->toContain('RACI')
        ->and($body)->toContain('baseline')
        ->and($body)->toContain('Review');
});

it('points each project tool at growth://rigor-levels in the rigor field description', function () {
    $tools = $this->postJson('/mcp/all', [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/list',
        'params' => ['per_page' => 200],
    ])->assertOk()->json('result.tools');

    $byName = collect($tools)->keyBy('name');

    $upsertRigor = $byName->get('upsert-project')['inputSchema']['properties']['rigor_level']['description'] ?? null;
    expect($upsertRigor)
        ->not->toBeNull()
        ->toContain('growth://rigor-levels')
        ->toContain('L2')
        ->toContain('L3');

    $createRigor = $byName->get('create-project')['inputSchema']['properties']['rigor_level']['description'] ?? null;
    expect($createRigor)
        ->not->toBeNull()
        ->toContain('growth://rigor-levels')
        ->toContain('L2')
        ->toContain('L3');

    $updateRigor = $byName->get('update-project')['inputSchema']['properties']['rigor_level']['description'] ?? null;
    expect($updateRigor)
        ->not->toBeNull()
        ->toContain('growth://rigor-levels')
        ->toContain('L2')
        ->toContain('L3');
});
