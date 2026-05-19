<?php

use App\Growth\Transitions\StartAnomalyInvestigation;
use App\Mcp\Servers\ReadonlyServer;
use App\Mcp\Tools\Common\WhoAmI;
use App\Mcp\Tools\Feedback\ListToolInvocations;
use App\Models\Anomaly;
use App\Models\Project;
use App\Models\StatusTransition;
use App\Models\ToolInvocation;
use App\Models\User;
use App\Support\CapabilitySurface;
use App\Support\SurfaceContext;
use Laravel\Passport\Passport;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    Passport::actingAs($this->user, ['mcp:use']);
});

it('records the bound capability surface on a tool invocation', function () {
    app(SurfaceContext::class)->set(CapabilitySurface::Readonly);

    ReadonlyServer::tool(WhoAmI::class)->assertOk();

    expect(ToolInvocation::sole()->acting_surface)->toBe('readonly');
});

it('records a null acting surface for an unbound session', function () {
    ReadonlyServer::tool(WhoAmI::class)->assertOk();

    expect(ToolInvocation::sole()->acting_surface)->toBeNull();
});

it('records the bound capability surface on a status transition audit row', function () {
    $project = Project::create([
        'workspace_id' => $this->user->active_workspace_id,
        'name' => 'Acting role',
        'rigor_level' => 2,
    ]);
    $anomaly = Anomaly::create([
        'project_id' => $project->id,
        'severity' => 'high',
        'status' => 'open',
        'summary' => 'Checkout fails',
        'description' => 'The cart total is wrong.',
    ]);

    app(SurfaceContext::class)->set(CapabilitySurface::Verification);

    $transition = (new StartAnomalyInvestigation)->apply($anomaly, $this->user, 'Triaging');

    expect($transition->acting_surface)->toBe('verification')
        ->and(StatusTransition::sole()->acting_surface)->toBe('verification');
});

it('leaves the transition acting surface null when the session is unbound', function () {
    $project = Project::create([
        'workspace_id' => $this->user->active_workspace_id,
        'name' => 'Acting role',
        'rigor_level' => 2,
    ]);
    $anomaly = Anomaly::create([
        'project_id' => $project->id,
        'severity' => 'high',
        'status' => 'open',
        'summary' => 'Checkout fails',
        'description' => 'The cart total is wrong.',
    ]);

    $transition = (new StartAnomalyInvestigation)->apply($anomaly, $this->user, 'Triaging');

    expect($transition->acting_surface)->toBeNull();
});

it('returns the acting surface through the list-tool-invocations MCP tool', function () {
    ToolInvocation::create([
        'workspace_id' => $this->user->active_workspace_id,
        'user_id' => $this->user->id,
        'acting_surface' => 'governance',
        'tool_name' => 'bound-call',
        'transport' => 'http',
        'success' => true,
        'duration_ms' => 5,
        'started_at' => now(),
        'completed_at' => now(),
    ]);

    ReadonlyServer::tool(ListToolInvocations::class, ['tool_name' => 'bound-call'])
        ->assertOk()
        ->assertStructuredContent(function ($json) {
            $json->where('results.0.acting_surface', 'governance')->etc();
        });
});

it('surfaces the acting surface on the tool-invocations feed', function () {
    $this->actingAs($this->user);

    ToolInvocation::create([
        'workspace_id' => $this->user->active_workspace_id,
        'user_id' => $this->user->id,
        'acting_surface' => 'verification',
        'tool_name' => 'bound-call',
        'transport' => 'http',
        'success' => true,
        'duration_ms' => 5,
        'started_at' => now(),
        'completed_at' => now(),
    ]);
    ToolInvocation::create([
        'workspace_id' => $this->user->active_workspace_id,
        'user_id' => $this->user->id,
        'acting_surface' => null,
        'tool_name' => 'unbound-call',
        'transport' => 'stdio',
        'success' => true,
        'duration_ms' => 5,
        'started_at' => now(),
        'completed_at' => now(),
    ]);

    Livewire::test('pages::tool-invocations')
        ->assertSee('verification')
        ->assertSee('unbound');
});
