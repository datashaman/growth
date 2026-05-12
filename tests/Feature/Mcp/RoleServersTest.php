<?php

use App\Mcp\Prompts\CheckReadiness;
use App\Mcp\Prompts\StartProject;
use App\Mcp\Servers\AllServer;
use App\Mcp\Servers\IntakeServer;
use App\Mcp\Servers\ReadonlyServer;
use App\Mcp\Servers\VerificationServer;
use App\Mcp\Tools\BuildEvidenceBundle;
use App\Mcp\Tools\DeleteProject;
use App\Mcp\Tools\GetProjectDashboardData;
use App\Mcp\Tools\ShowProjectDashboard;
use App\Mcp\Tools\UpsertCapability;
use App\Mcp\Tools\UpsertProject;
use App\Models\CheckRunEvidence;
use App\Models\Project;
use App\Models\Requirement;
use App\Models\User;
use App\Models\WorkItem;
use App\Models\WorkItemDeliveryLink;
use Laravel\Passport\Passport;

it('exposes role-specific MCP metadata surfaces', function () {
    $user = User::factory()->create();
    Passport::actingAs($user, ['mcp:use']);

    $intakeTools = $this->postJson('/mcp/intake', [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/list',
    ])->assertOk()->json('result.tools');

    $planningTools = $this->postJson('/mcp/planning', [
        'jsonrpc' => '2.0',
        'id' => 2,
        'method' => 'tools/list',
    ])->assertOk()->json('result.tools');

    $resources = $this->postJson('/mcp/readonly', [
        'jsonrpc' => '2.0',
        'id' => 3,
        'method' => 'resources/templates/list',
    ])->assertOk()->json('result.resourceTemplates');

    expect(collect($intakeTools)->pluck('name')->all())->toContain('upsert-citation', 'upsert-capability')
        ->and(collect($planningTools)->pluck('name')->all())->toContain('upsert-work-item', 'summarize-plan-capacity')
        ->and(collect($resources)->pluck('uriTemplate')->all())->toContain('growth://projects/{project}/capabilities');
});

it('exposes the complete MCP surface through the all server', function () {
    $user = User::factory()->create();
    Passport::actingAs($user, ['mcp:use']);

    $tools = $this->postJson('/mcp/all', [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/list',
        'params' => [
            'per_page' => 200,
        ],
    ])->assertOk()->json('result.tools');

    $resources = $this->postJson('/mcp/all', [
        'jsonrpc' => '2.0',
        'id' => 2,
        'method' => 'resources/templates/list',
        'params' => [
            'per_page' => 200,
        ],
    ])->assertOk()->json('result.resourceTemplates');

    $prompts = $this->postJson('/mcp/all', [
        'jsonrpc' => '2.0',
        'id' => 3,
        'method' => 'prompts/list',
    ])->assertOk()->json('result.prompts');

    expect(collect($tools)->pluck('name')->all())->toContain(
        'upsert-project',
        'upsert-capability',
        'upsert-architecture-view',
        'upsert-work-item',
        'upsert-verification-plan',
        'upsert-review',
        'upsert-change-request',
        'trace-query',
    )->and(collect($resources)->pluck('uriTemplate')->all())->toContain(
        'growth://projects/{project}/capabilities',
        'growth://projects/{project}/sdd',
    )->and(collect($prompts)->pluck('name')->all())->toContain(
        'start-project',
        'capture-intent',
        'plan-slice',
        'check-readiness',
    );

    AllServer::tool(UpsertProject::class, [
        'name' => 'All Surface',
        'description' => 'Project created through the all server.',
        'rigor_level' => 2,
    ])->assertOk();
});

it('exposes the project dashboard app through readonly and all servers', function () {
    $user = User::factory()->create();
    Passport::actingAs($user, ['mcp:use']);

    $initialize = $this->postJson('/mcp/readonly', [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'initialize',
        'params' => [
            'protocolVersion' => '2025-11-25',
            'clientInfo' => [
                'name' => 'test',
                'version' => '1.0.0',
            ],
            'capabilities' => [],
        ],
    ])->assertOk()->json('result.capabilities');

    $readonlyTools = $this->postJson('/mcp/readonly', [
        'jsonrpc' => '2.0',
        'id' => 2,
        'method' => 'tools/list',
    ])->assertOk()->json('result.tools');

    $readonlyResources = $this->postJson('/mcp/readonly', [
        'jsonrpc' => '2.0',
        'id' => 3,
        'method' => 'resources/list',
    ])->assertOk()->json('result.resources');

    $allTools = $this->postJson('/mcp/all', [
        'jsonrpc' => '2.0',
        'id' => 4,
        'method' => 'tools/list',
        'params' => [
            'per_page' => 200,
        ],
    ])->assertOk()->json('result.tools');

    expect($initialize)->toHaveKey('io.modelcontextprotocol/ui')
        ->and(collect($readonlyTools)->pluck('name')->all())->toContain(
            'show-project-dashboard',
            'get-project-dashboard-data',
        )
        ->and(collect($readonlyTools)->firstWhere('name', 'show-project-dashboard')['_meta']['ui']['resourceUri'])->toBe('ui://resources/project-dashboard-app')
        ->and(collect($readonlyTools)->firstWhere('name', 'get-project-dashboard-data')['_meta']['ui']['visibility'])->toBe(['app'])
        ->and(collect($readonlyResources)->pluck('uri')->all())->toContain('ui://resources/project-dashboard-app')
        ->and(collect($readonlyResources)->firstWhere('uri', 'ui://resources/project-dashboard-app')['mimeType'])->toBe('text/html;profile=mcp-app')
        ->and(collect($allTools)->pluck('name')->all())->toContain(
            'show-project-dashboard',
            'get-project-dashboard-data',
        );
});

it('returns read-only data for the project dashboard app', function () {
    $project = Project::create([
        'name' => 'Dashboard Project',
        'description' => 'Visible in the MCP app.',
        'integrity_level' => 2,
    ]);
    Requirement::create([
        'project_id' => $project->id,
        'doc' => 'srs',
        'type' => 'functional',
        'text' => 'The app shall show dashboard data.',
        'priority' => 'high',
    ]);

    ReadonlyServer::tool(ShowProjectDashboard::class, [
        'project_id' => $project->id,
    ])->assertOk()
        ->assertSee('Project dashboard loaded.')
        ->assertSee($project->id);

    ReadonlyServer::tool(GetProjectDashboardData::class)->assertOk()
        ->assertSee('Dashboard Project')
        ->assertStructuredContent(fn ($json) => $json
            ->has('projects', 1)
            ->where('selected_project', null)
        );

    ReadonlyServer::tool(GetProjectDashboardData::class, [
        'project_id' => $project->id,
    ])->assertOk()
        ->assertSee('Dashboard Project')
        ->assertSee('growth://projects/'.$project->id.'/capabilities')
        ->assertSee('readiness')
        ->assertSee('implementation')
        ->assertSee('schedule')
        ->assertSee('capacity');

    ReadonlyServer::tool(GetProjectDashboardData::class, [
        'project_id' => 'missing-project',
    ])->assertHasErrors(['The selected project id is invalid.']);
});

it('renders the project dashboard app resource as MCP app HTML', function () {
    readResource(ReadonlyServer::class, 'ui://resources/project-dashboard-app')
        ->assertOk()
        ->assertSee('createMcpApp')
        ->assertSee('get-project-dashboard-data')
        ->assertSee('lookup-term')
        ->assertSee('Terms')
        ->assertSee('app.readResource')
        ->assertSee('data-resource-uri')
        ->assertSee('Project Dashboard');
});

it('serves a local browser host for the project dashboard app', function () {
    $project = Project::create([
        'name' => 'Hosted Dashboard',
        'description' => 'Rendered by the local MCP app host.',
        'integrity_level' => 2,
    ]);

    $this->get('/mcp-apps/project-dashboard')
        ->assertOk()
        ->assertSee('Growth Local MCP App Host')
        ->assertSee('createMcpApp')
        ->assertSee('mcp-app-frame');

    $this->postJson('/mcp-apps/project-dashboard/rpc', [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/call',
        'params' => [
            'name' => 'get-project-dashboard-data',
            'arguments' => [
                'project_id' => $project->id,
            ],
        ],
    ])->assertOk()
        ->assertJsonPath('jsonrpc', '2.0')
        ->assertJsonPath('id', 1)
        ->assertJsonPath('result.isError', false)
        ->assertJsonPath('result.structuredContent.selected_project.name', 'Hosted Dashboard');

    $this->postJson('/mcp-apps/project-dashboard/rpc', [
        'jsonrpc' => '2.0',
        'id' => 2,
        'method' => 'resources/read',
        'params' => [
            'uri' => "growth://projects/{$project->id}",
        ],
    ])->assertOk()
        ->assertJsonPath('jsonrpc', '2.0')
        ->assertJsonPath('id', 2)
        ->assertJsonPath('result.contents.0.mimeType', 'application/json')
        ->assertJsonPath('result.contents.0.uri', "growth://projects/{$project->id}")
        ->assertSee('Hosted Dashboard');
});

it('upserts projects and capabilities through the intake server', function () {
    $projectResponse = IntakeServer::tool(UpsertProject::class, [
        'name' => 'TodoMVC',
        'description' => 'Standalone todo app.',
        'rigor_level' => 2,
    ]);

    $projectResponse->assertOk();
    $projectId = Project::where('name', 'TodoMVC')->sole()->id;

    IntakeServer::tool(UpsertProject::class, [
        'id' => $projectId,
        'name' => 'TodoMVC v2',
        'description' => 'Standalone todo app with local persistence.',
        'rigor_level' => 3,
    ])->assertOk()
        ->assertSee('TodoMVC v2');

    $capabilityResponse = IntakeServer::tool(UpsertCapability::class, [
        'project_id' => $projectId,
        'layer' => 'software',
        'type' => 'functional',
        'text' => 'The app shall add a todo when the user submits non-empty text.',
        'acceptance_checks' => [
            'Submitting non-empty text creates one active todo.',
        ],
        'priority' => 'high',
    ]);

    $capabilityResponse->assertOk()
        ->assertSee('software');

    expect(Project::find($projectId)?->integrity_level)->toBe(3)
        ->and(Requirement::where('project_id', $projectId)->first()?->doc)->toBe('srs');
});

it('deletes projects through the intake server with exact-name confirmation', function () {
    $project = Project::create([
        'name' => 'Disposable',
        'integrity_level' => 2,
    ]);

    IntakeServer::tool(DeleteProject::class, [
        'id' => $project->id,
        'confirm_name' => 'Wrong name',
    ])->assertHasErrors(['Confirmation mismatch']);

    expect(Project::find($project->id))->not->toBeNull();

    IntakeServer::tool(DeleteProject::class, [
        'id' => $project->id,
        'confirm_name' => 'Disposable',
    ])->assertOk()
        ->assertSee('deleted');

    expect(Project::find($project->id))->toBeNull();
});

it('renders existing project data through readonly resources', function () {
    $project = Project::create([
        'name' => 'TodoMVC',
        'description' => 'Standalone todo app.',
        'integrity_level' => 2,
    ]);
    Requirement::create([
        'project_id' => $project->id,
        'doc' => 'srs',
        'type' => 'functional',
        'text' => 'The app shall persist todos locally.',
        'acceptance_criteria' => ['Reloading the page restores saved todos.'],
        'priority' => 'high',
    ]);

    $resource = readResource(ReadonlyServer::class, "growth://projects/{$project->id}/capabilities");

    $resource->assertOk()
        ->assertSee('Capabilities - TodoMVC')
        ->assertSee('The app shall persist todos locally.');
});

it('renders evidence resources with human readable delivery context', function () {
    $project = Project::create([
        'name' => 'TodoMVC',
        'integrity_level' => 2,
    ]);
    $workItem = WorkItem::create([
        'project_id' => $project->id,
        'kind' => 'task',
        'name' => 'Implement persistence',
        'status' => 'done',
    ]);
    $deliveryLink = WorkItemDeliveryLink::create([
        'work_item_id' => $workItem->id,
        'type' => 'commit',
        'ref' => 'abc123',
        'url' => 'https://example.test/commit/abc123',
        'description' => 'Persistence implementation committed.',
    ]);
    CheckRunEvidence::create([
        'work_item_delivery_link_id' => $deliveryLink->id,
        'provider' => 'github',
        'name' => 'tests',
        'run_ref' => 'run-1',
        'status' => 'completed',
        'conclusion' => 'success',
        'url' => 'https://example.test/runs/1',
    ]);

    $resource = readResource(ReadonlyServer::class, "growth://projects/{$project->id}/evidence");

    $resource->assertOk()
        ->assertSee('Persistence implementation committed.')
        ->assertSee('https://example.test/commit/abc123')
        ->assertSee('github')
        ->assertSee('run-1');
});

it('builds an evidence bundle through the verification server', function () {
    $project = Project::create([
        'name' => 'TodoMVC',
        'integrity_level' => 2,
    ]);

    $response = VerificationServer::tool(BuildEvidenceBundle::class, [
        'project_id' => $project->id,
    ]);

    $response->assertOk()
        ->assertSee("growth://projects/{$project->id}/capabilities");
});

it('exposes prompts on the role servers that use them', function () {
    $user = User::factory()->create();
    Passport::actingAs($user, ['mcp:use']);

    $intakePrompts = $this->postJson('/mcp/intake', [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'prompts/list',
    ])->assertOk()->json('result.prompts');

    $planningPrompts = $this->postJson('/mcp/planning', [
        'jsonrpc' => '2.0',
        'id' => 2,
        'method' => 'prompts/list',
    ])->assertOk()->json('result.prompts');

    $verificationPrompts = $this->postJson('/mcp/verification', [
        'jsonrpc' => '2.0',
        'id' => 3,
        'method' => 'prompts/list',
    ])->assertOk()->json('result.prompts');

    expect(collect($intakePrompts)->pluck('name')->all())->toContain('start-project', 'capture-intent')
        ->and(collect($planningPrompts)->pluck('name')->all())->toContain('plan-slice')
        ->and(collect($verificationPrompts)->pluck('name')->all())->toContain('check-readiness');
});

it('returns usable prompt messages from role servers', function () {
    IntakeServer::prompt(StartProject::class, [
        'name' => 'TodoMVC',
        'summary' => 'A small browser todo app.',
        'rigor_level' => 2,
    ])->assertOk()
        ->assertSee('upsert-project')
        ->assertSee('growth://playbook')
        ->assertSee('TodoMVC');

    $project = Project::create([
        'name' => 'TodoMVC',
        'integrity_level' => 2,
    ]);

    VerificationServer::prompt(CheckReadiness::class, [
        'project_id' => $project->id,
    ])->assertOk()
        ->assertSee('Overall readiness')
        ->assertSee('Recommend the next action');
});
