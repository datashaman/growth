<?php

use App\Models\ChangeImpact;
use App\Models\Project;
use App\Models\Requirement;
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

it('creates and updates a change request with a non-mutating references impact', function () {
    $user = User::factory()->create();
    Passport::actingAs($user, ['mcp:use']);

    $project = Project::create([
        'workspace_id' => $user->active_workspace_id,
        'name' => 'References',
        'rigor_level' => 2,
    ]);
    $firstRequirement = Requirement::create([
        'project_id' => $project->id,
        'doc' => 'srs',
        'type' => 'functional',
        'text' => 'The register shall cite supporting evidence.',
    ]);
    $secondRequirement = Requirement::create([
        'project_id' => $project->id,
        'doc' => 'srs',
        'type' => 'functional',
        'text' => 'The dashboard shall summarize change context.',
    ]);

    $created = $this->postJson('/mcp/governance', [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/call',
        'params' => [
            'name' => 'upsert-change-request',
            'arguments' => [
                'project_id' => $project->id,
                'title' => 'Document telemetry context',
                'category' => 'requirements',
                'impacts' => [[
                    'type' => 'requirement',
                    'id' => $firstRequirement->id,
                    'impact_kind' => 'references',
                    'description' => 'Context only; this requirement is not being changed.',
                ]],
            ],
        ],
    ])->assertOk()->json('result.structuredContent');

    expect($created['impacts'])->toBe(1)
        ->and(ChangeImpact::where('change_request_id', $created['id'])->sole()->impact_kind)->toBe('references');

    $this->postJson('/mcp/governance', [
        'jsonrpc' => '2.0',
        'id' => 2,
        'method' => 'tools/call',
        'params' => [
            'name' => 'upsert-change-request',
            'arguments' => [
                'id' => $created['id'],
                'project_id' => $project->id,
                'title' => 'Document telemetry context',
                'category' => 'requirements',
                'impacts' => [[
                    'type' => 'requirement',
                    'id' => $secondRequirement->id,
                    'impact_kind' => 'references',
                    'description' => 'Replacement context reference.',
                ]],
            ],
        ],
    ])->assertOk();

    $impact = ChangeImpact::where('change_request_id', $created['id'])->sole();
    expect($impact->impact_kind)->toBe('references')
        ->and($impact->impactable_id)->toBe($secondRequirement->id)
        ->and($impact->description)->toBe('Replacement context reference.');
});

it('warns immediately when a change request has no impacted artifacts', function () {
    $user = User::factory()->create();
    Passport::actingAs($user, ['mcp:use']);

    $project = Project::create([
        'workspace_id' => $user->active_workspace_id,
        'name' => 'No impacts',
        'rigor_level' => 2,
    ]);

    $created = $this->postJson('/mcp/governance', [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/call',
        'params' => [
            'name' => 'upsert-change-request',
            'arguments' => [
                'project_id' => $project->id,
                'title' => 'Clarify telemetry scope',
                'category' => 'scope',
            ],
        ],
    ])->assertOk()->json('result.structuredContent');

    expect($created['impacts'])->toBe(0)
        ->and($created['warnings'])->toContain('change_control will remain unhealthy until at least one impacted artifact is recorded on this change request.');
});

it('preserves omitted optional fields and impacts when updating a change request', function () {
    $user = User::factory()->create();
    Passport::actingAs($user, ['mcp:use']);

    $project = Project::create([
        'workspace_id' => $user->active_workspace_id,
        'name' => 'Merge updates',
        'rigor_level' => 2,
    ]);
    $requirement = Requirement::create([
        'project_id' => $project->id,
        'doc' => 'srs',
        'type' => 'functional',
        'text' => 'The dashboard shall surface change-control health.',
    ]);

    $created = $this->postJson('/mcp/governance', [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/call',
        'params' => [
            'name' => 'upsert-change-request',
            'arguments' => [
                'project_id' => $project->id,
                'title' => 'Record gate warning',
                'description' => 'Initial description',
                'rationale' => 'Initial rationale',
                'category' => 'requirements',
                'priority' => 'high',
                'impacts' => [[
                    'type' => 'requirement',
                    'id' => $requirement->id,
                    'impact_kind' => 'modifies',
                    'description' => 'Dashboard copy changes.',
                ]],
            ],
        ],
    ])->assertOk()->json('result.structuredContent');

    $updated = $this->postJson('/mcp/governance', [
        'jsonrpc' => '2.0',
        'id' => 2,
        'method' => 'tools/call',
        'params' => [
            'name' => 'upsert-change-request',
            'arguments' => [
                'id' => $created['id'],
                'project_id' => $project->id,
                'title' => 'Record gate warning precisely',
                'category' => 'requirements',
            ],
        ],
    ])->assertOk()->json('result.structuredContent');

    $change = $project->changeRequests()->whereKey($created['id'])->firstOrFail();

    expect($updated['impacts'])->toBe(1)
        ->and($updated['warnings'])->toBe([])
        ->and($change->description)->toBe('Initial description')
        ->and($change->rationale)->toBe('Initial rationale')
        ->and($change->priority)->toBe('high')
        ->and($change->impacts()->count())->toBe(1);
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

it('documents merge behavior on the upsert-change-request tool schema', function () {
    Passport::actingAs(User::factory()->create(), ['mcp:use']);

    $tools = $this->postJson('/mcp/governance', [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/list',
    ])->assertOk()->json('result.tools');

    $changeTool = collect($tools)->firstWhere('name', 'upsert-change-request');

    expect($changeTool)->not->toBeNull()
        ->and($changeTool['description'] ?? '')->toContain('Updates are merge-style')
        ->and($changeTool['inputSchema']['properties']['description']['description'] ?? '')->toContain('omit to preserve')
        ->and($changeTool['inputSchema']['properties']['impacts']['description'] ?? '')->toContain('omit impacts to preserve existing links')
        ->and($changeTool['inputSchema']['properties']['impacts']['description'] ?? '')->toContain('pass [] to explicitly clear');
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
