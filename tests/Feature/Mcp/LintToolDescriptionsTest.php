<?php

use App\Models\User;
use Laravel\Passport\Passport;

beforeEach(function () {
    Passport::actingAs(User::factory()->create(), ['mcp:use']);
});

function toolDescription(string $endpoint, string $name): ?string
{
    $tools = test()->postJson($endpoint, [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/list',
    ])->assertOk()->json('result.tools');

    return collect($tools)->firstWhere('name', $name)['description'] ?? null;
}

it('cross-references per-section linters from lint-project', function () {
    $description = toolDescription('/mcp/all', 'lint-project');

    expect($description)->not->toBeNull()
        ->and($description)->toContain('sections.planning matches lint-pmp')
        ->and($description)->toContain('sections.verification matches lint-verification')
        ->and($description)->toContain('sections.architecture matches lint-architecture');
});

it('cross-references lint-project from lint-pmp', function () {
    $description = toolDescription('/mcp/all', 'lint-pmp');

    expect($description)->not->toBeNull()
        ->and($description)->toContain('lint-project sections.planning');
});
