<?php

use App\Mcp\Prompts\CheckReadiness;
use App\Mcp\Prompts\StartProject;
use App\Mcp\Servers\AllServer;
use App\Mcp\Servers\IntakeServer;
use App\Mcp\Servers\ReadonlyServer;
use App\Mcp\Servers\VerificationServer;
use App\Mcp\Tools\Assurance\BuildEvidenceBundle;
use App\Mcp\Tools\Dashboard\GetProjectDashboardData;
use App\Mcp\Tools\Dashboard\ShowProjectDashboard;
use App\Mcp\Tools\Projects\DeleteProject;
use App\Mcp\Tools\Projects\UpsertProject;
use App\Mcp\Tools\Requirements\UpsertRequirements;
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

    expect(collect($intakeTools)->pluck('name')->all())->toContain('upsert-citation', 'upsert-requirements')
        ->and(collect($planningTools)->pluck('name')->all())->toContain('upsert-work-items', 'summarize-plan-capacity')
        ->and(collect($resources)->pluck('uriTemplate')->all())->toContain('growth://projects/{project}/requirements');
});

it('exposes the doctor tool on every role server', function (string $endpoint) {
    Passport::actingAs(User::factory()->create(), ['mcp:use']);

    $tools = $this->postJson($endpoint, [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/list',
        'params' => ['per_page' => 200],
    ])->assertOk()->json('result.tools');

    expect(collect($tools)->pluck('name')->all())->toContain('doctor');
})->with([
    '/mcp/intake',
    '/mcp/management',
    '/mcp/architecture',
    '/mcp/planning',
    '/mcp/verification',
    '/mcp/governance',
    '/mcp/readonly',
]);

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
        'upsert-requirements',
        'upsert-architecture-view',
        'upsert-work-items',
        'upsert-verification-plan',
        'upsert-review',
        'upsert-change-request',
        'trace-query',
        'search-feedback',
        'send-feedback',
        'doctor',
    )->and(collect($resources)->pluck('uriTemplate')->all())->toContain(
        'growth://projects/{project}/requirements',
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
        'workspace_id' => User::factory()->create()->active_workspace_id,
        'name' => 'Dashboard Project',
        'description' => 'Visible in the MCP app.',
        'rigor_level' => 2,
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
        ->assertSee('growth://projects/'.$project->id.'/requirements')
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

it('upserts projects and requirements through the intake server', function () {
    Passport::actingAs(User::factory()->create(), ['mcp:use']);

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

    $requirementResponse = IntakeServer::tool(UpsertRequirements::class, [
        'items' => [
            [
                'project_id' => $projectId,
                'layer' => 'software',
                'type' => 'functional',
                'text' => 'The app shall add a todo when the user submits non-empty text.',
                'acceptance_checks' => [
                    'Submitting non-empty text creates one active todo.',
                ],
                'priority' => 'high',
            ],
        ],
    ]);

    $requirementResponse->assertOk()
        ->assertSee('software');

    expect(Project::find($projectId)?->rigor_level)->toBe(3)
        ->and(Requirement::where('project_id', $projectId)->first()?->doc)->toBe('srs');
});

it('deletes projects through the intake server with exact-name confirmation', function () {
    $project = Project::create([
        'workspace_id' => User::factory()->create()->active_workspace_id,
        'name' => 'Disposable',
        'rigor_level' => 2,
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
        'workspace_id' => User::factory()->create()->active_workspace_id,
        'name' => 'TodoMVC',
        'description' => 'Standalone todo app.',
        'rigor_level' => 2,
    ]);
    Requirement::create([
        'project_id' => $project->id,
        'doc' => 'srs',
        'type' => 'functional',
        'text' => 'The app shall persist todos locally.',
        'acceptance_criteria' => ['Reloading the page restores saved todos.'],
        'priority' => 'high',
    ]);

    $resource = readResource(ReadonlyServer::class, "growth://projects/{$project->id}/requirements");

    $resource->assertOk()
        ->assertSee('Requirements - TodoMVC')
        ->assertSee('The app shall persist todos locally.');
});

it('renders evidence resources with human readable delivery context', function () {
    $project = Project::create([
        'workspace_id' => User::factory()->create()->active_workspace_id,
        'name' => 'TodoMVC',
        'rigor_level' => 2,
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
        'workspace_id' => User::factory()->create()->active_workspace_id,
        'name' => 'TodoMVC',
        'rigor_level' => 2,
    ]);

    $response = VerificationServer::tool(BuildEvidenceBundle::class, [
        'project_id' => $project->id,
    ]);

    $response->assertOk()
        ->assertSee("growth://projects/{$project->id}/requirements");
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
        'workspace_id' => User::factory()->create()->active_workspace_id,
        'name' => 'TodoMVC',
        'rigor_level' => 2,
    ]);

    VerificationServer::prompt(CheckReadiness::class, [
        'project_id' => $project->id,
    ])->assertOk()
        ->assertSee('Overall readiness')
        ->assertSee('Recommend the next action');
});
