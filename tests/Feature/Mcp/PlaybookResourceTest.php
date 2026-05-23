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

it('documents post-baseline change management', function () {
    $body = playbookBody();

    expect($body)->not->toBeNull()
        ->toContain('Post-baseline change management')
        ->toContain('Use an anomaly for an observed defect')
        ->toContain('use a change request when the team is proposing or recording an intentional alteration')
        ->toContain('Every change request needs impacted artifacts')
        ->toContain('`change_control` readiness gate unhealthy')
        ->toContain('Decision rationale is part of the control record')
        ->toContain('create the proposed change request with impacts')
        ->toContain('submit it for review')
        ->toContain('approve/reject/defer it with rationale')
        ->toContain('mark the approved change request implemented');
});
