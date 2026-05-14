<?php

use App\Mcp\Servers\GovernanceServer;
use App\Mcp\Servers\IntakeServer;
use App\Mcp\Servers\ReadonlyServer;
use App\Mcp\Tools\Dashboard\ShowCapabilityExplorer;
use App\Mcp\Tools\Dashboard\ShowGateStatus;
use App\Mcp\Tools\Dashboard\ShowTraceGraph;
use App\Models\Project;
use App\Models\User;
use Laravel\Passport\Passport;

beforeEach(function () {
    $this->user = User::factory()->create();
    Passport::actingAs($this->user, ['mcp:use']);
});

it('lists the show-* app tools on the right servers with the right resourceUri', function () {
    $readonlyTools = $this->postJson('/mcp/readonly', [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/list',
        'params' => ['per_page' => 200],
    ])->assertOk()->json('result.tools');

    $governanceTools = $this->postJson('/mcp/governance', [
        'jsonrpc' => '2.0',
        'id' => 2,
        'method' => 'tools/list',
        'params' => ['per_page' => 200],
    ])->assertOk()->json('result.tools');

    $intakeTools = $this->postJson('/mcp/intake', [
        'jsonrpc' => '2.0',
        'id' => 3,
        'method' => 'tools/list',
        'params' => ['per_page' => 200],
    ])->assertOk()->json('result.tools');

    expect(collect($readonlyTools)->pluck('name')->all())->toContain(
        'show-gate-status',
        'show-capability-explorer',
        'show-trace-graph',
    )
        ->and(collect($readonlyTools)->firstWhere('name', 'show-gate-status')['_meta']['ui']['resourceUri'])->toBe('ui://resources/gate-status')
        ->and(collect($readonlyTools)->firstWhere('name', 'show-capability-explorer')['_meta']['ui']['resourceUri'])->toBe('ui://resources/capability-explorer')
        ->and(collect($readonlyTools)->firstWhere('name', 'show-trace-graph')['_meta']['ui']['resourceUri'])->toBe('ui://resources/trace-graph')
        ->and(collect($governanceTools)->pluck('name')->all())->toContain('show-gate-status')
        ->and(collect($intakeTools)->pluck('name')->all())->toContain('show-capability-explorer');
});

it('returns a confirmation payload from each show tool with the selected project_id', function () {
    $project = Project::create([
        'user_id' => $this->user->id,
        'name' => 'Show app tools project',
        'description' => 'Used to verify show-* tool payloads.',
        'rigor_level' => 2,
    ]);

    ReadonlyServer::tool(ShowGateStatus::class, ['project_id' => $project->id])
        ->assertOk()
        ->assertSee('Gate status app loaded.')
        ->assertSee($project->id);

    ReadonlyServer::tool(ShowCapabilityExplorer::class, ['project_id' => $project->id])
        ->assertOk()
        ->assertSee('Capability explorer app loaded.')
        ->assertSee($project->id);

    ReadonlyServer::tool(ShowTraceGraph::class, ['project_id' => $project->id])
        ->assertOk()
        ->assertSee('Trace graph app loaded.')
        ->assertSee($project->id);

    GovernanceServer::tool(ShowGateStatus::class)->assertOk()->assertSee('Gate status app loaded.');
    IntakeServer::tool(ShowCapabilityExplorer::class)->assertOk()->assertSee('Capability explorer app loaded.');
});
