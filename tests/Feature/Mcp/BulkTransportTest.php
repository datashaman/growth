<?php

use App\Models\Project;
use App\Models\User;
use Laravel\Passport\Passport;

it('round-trips upsert-capabilities through the HTTP MCP transport', function () {
    $user = User::factory()->create();
    Passport::actingAs($user, ['mcp:use']);

    $project = Project::create([
        'user_id' => $user->id,
        'name' => 'Transport',
        'rigor_level' => 2,
    ]);

    $response = $this->postJson('/mcp/intake', [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/call',
        'params' => [
            'name' => 'upsert-capabilities',
            'arguments' => [
                'items' => [
                    [
                        'project_id' => $project->id,
                        'layer' => 'software',
                        'type' => 'functional',
                        'text' => 'The app shall round-trip through the MCP transport.',
                        'priority' => 'medium',
                    ],
                    [
                        'project_id' => $project->id,
                        'layer' => 'software',
                        'type' => 'functional',
                        'text' => 'no',
                    ],
                ],
            ],
        ],
    ]);

    $response->assertOk();

    $items = $response->json('result.structuredContent.items');

    expect($items)->toHaveCount(2)
        ->and($items[0]['ok'])->toBeTrue()
        ->and($items[1]['ok'])->toBeFalse()
        ->and($items[1]['errors'])->toHaveKey('text');
});

it('returns a clear cap error from the HTTP transport when items exceed 100', function () {
    $user = User::factory()->create();
    Passport::actingAs($user, ['mcp:use']);

    $project = Project::create([
        'user_id' => $user->id,
        'name' => 'Cap',
        'rigor_level' => 2,
    ]);

    $items = array_fill(0, 101, [
        'project_id' => $project->id,
        'layer' => 'software',
        'type' => 'functional',
        'text' => 'A capability of sufficient length.',
    ]);

    $response = $this->postJson('/mcp/intake', [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/call',
        'params' => [
            'name' => 'upsert-capabilities',
            'arguments' => ['items' => $items],
        ],
    ]);

    $response->assertOk();
    $payload = $response->json('result');

    expect($payload['isError'] ?? false)->toBeTrue();
    expect(json_encode($payload))->toContain('Batches are capped at 100 items per call.');
});
