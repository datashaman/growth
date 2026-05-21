<?php

use App\Models\ChangeImpact;
use App\Models\Project;
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
        ->and($properties)->not->toHaveKey('decided_at');
});

it('exposes decision_rationale on the upsert-change-request tool schema for backfill', function () {
    Passport::actingAs(User::factory()->create(), ['mcp:use']);

    $tools = $this->postJson('/mcp/governance', [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/list',
    ])->assertOk()->json('result.tools');

    $changeTool = collect($tools)->firstWhere('name', 'upsert-change-request');

    expect($changeTool)->not->toBeNull()
        ->and($changeTool['inputSchema']['properties'] ?? [])->toHaveKey('decision_rationale');
});

it('backfills decision_rationale on an already-decided change request', function () {
    $user = User::factory()->create();
    Passport::actingAs($user, ['mcp:use']);

    $project = Project::create([
        'workspace_id' => $user->active_workspace_id,
        'name' => 'Backfill',
        'rigor_level' => 2,
    ]);
    $change = $project->changeRequests()->create([
        'title' => 'Decided without rationale',
        'category' => 'scope',
        'status' => 'approved',
        'priority' => 'medium',
        'decision' => 'approved',
    ]);

    $this->postJson('/mcp/governance', [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/call',
        'params' => [
            'name' => 'upsert-change-request',
            'arguments' => [
                'id' => $change->id,
                'project_id' => $project->id,
                'title' => $change->title,
                'category' => 'scope',
                'decision_rationale' => 'Backfilled rationale',
            ],
        ],
    ])->assertOk();

    expect($change->fresh()->decision_rationale)->toBe('Backfilled rationale');
});
