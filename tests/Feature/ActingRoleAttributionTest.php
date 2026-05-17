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
use App\Support\OperatingRole;
use App\Support\RoleContext;
use Laravel\Passport\Passport;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    Passport::actingAs($this->user, ['mcp:use']);
});

it('records the bound operating role on a tool invocation', function () {
    app(RoleContext::class)->set(OperatingRole::Readonly);

    ReadonlyServer::tool(WhoAmI::class)->assertOk();

    expect(ToolInvocation::sole()->acting_role)->toBe('readonly');
});

it('records a null acting role for an unbound session', function () {
    ReadonlyServer::tool(WhoAmI::class)->assertOk();

    expect(ToolInvocation::sole()->acting_role)->toBeNull();
});

it('records the bound operating role on a status transition audit row', function () {
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

    app(RoleContext::class)->set(OperatingRole::Verification);

    $transition = (new StartAnomalyInvestigation)->apply($anomaly, $this->user, 'Triaging');

    expect($transition->acting_role)->toBe('verification')
        ->and(StatusTransition::sole()->acting_role)->toBe('verification');
});

it('leaves the transition acting role null when the session is unbound', function () {
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

    expect($transition->acting_role)->toBeNull();
});

it('returns the acting role through the list-tool-invocations MCP tool', function () {
    ToolInvocation::create([
        'workspace_id' => $this->user->active_workspace_id,
        'user_id' => $this->user->id,
        'acting_role' => 'governance',
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
            $json->where('results.0.acting_role', 'governance')->etc();
        });
});

it('surfaces the acting role on the tool-invocations feed', function () {
    $this->actingAs($this->user);

    ToolInvocation::create([
        'workspace_id' => $this->user->active_workspace_id,
        'user_id' => $this->user->id,
        'acting_role' => 'verification',
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
        'acting_role' => null,
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
