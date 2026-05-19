<?php

use App\Models\User;
use Laravel\Passport\Passport;

beforeEach(function () {
    Passport::actingAs(User::factory()->create(), ['mcp:use']);
});

function playbookBody(): ?string
{
    $response = test()->postJson('/mcp/readonly', [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'resources/read',
        'params' => ['uri' => 'growth://playbook'],
    ])->assertOk()->json('result.contents');

    return collect($response)->firstWhere('uri', 'growth://playbook')['text'] ?? null;
}

it('serves the playbook resource', function () {
    expect(playbookBody())->not->toBeNull()
        ->toContain('# Growth Playbook');
});

it('documents the brownfield backfill procedure', function () {
    $body = playbookBody();

    expect($body)->not->toBeNull()
        ->toContain('Adopting and backfilling an existing repository')
        ->toContain('recovered fact')
        ->toContain('assumed intent')
        ->toContain('adopt-project')
        ->toContain('apply-manifest')
        ->toContain('`merge`')
        ->toContain('dry_run')
        ->toContain('upsert-source')
        ->toContain('kind: source')
        ->toContain('cite-artifact')
        ->toContain('design_view')
        ->toContain('technical_review')
        ->toContain('upsert-review')
        ->toContain('start-review')
        ->toContain('upsert-review-finding')
        ->toContain('close-review');
});
