<?php

use App\Growth\Plan\PlanBaseliner;
use App\Mcp\Servers\ReadonlyServer;
use App\Mcp\Tools\Common\WhoAmI;
use App\Mcp\Tools\Feedback\SendFeedback;
use App\Models\Agent;
use App\Models\Project;
use App\Models\ProjectPlan;
use App\Models\ToolFeedback;
use App\Models\ToolInvocation;
use App\Models\User;
use App\Support\AgentContext;
use Laravel\Passport\Passport;

beforeEach(function () {
    $this->user = User::factory()->create();
    Passport::actingAs($this->user, ['mcp:use']);
    $this->project = Project::create([
        'workspace_id' => $this->user->active_workspace_id,
        'name' => 'Attribution',
        'rigor_level' => 2,
    ]);
    $this->agent = Agent::create([
        'project_id' => $this->project->id,
        'name' => 'coder',
        'kind' => 'coding',
    ]);
});

it('records the bound agent on a tool invocation', function () {
    app(AgentContext::class)->set($this->agent);

    ReadonlyServer::tool(WhoAmI::class)->assertOk();

    expect(ToolInvocation::sole()->agent_id)->toBe($this->agent->id);
});

it('records a null agent id on a tool invocation for an unbound session', function () {
    ReadonlyServer::tool(WhoAmI::class)->assertOk();

    expect(ToolInvocation::sole()->agent_id)->toBeNull();
});

it('records the bound agent on feedback regardless of the feedback project', function () {
    app(AgentContext::class)->set($this->agent);

    ReadonlyServer::tool(SendFeedback::class, [
        'category' => 'difficulty',
        'summary' => 'The schema is confusing',
        'body' => 'I could not tell which ids were required.',
        'project_id' => $this->project->id,
    ])->assertOk();

    expect(ToolFeedback::sole()->agent_id)->toBe($this->agent->id);
});

it('records a null agent id on feedback for an unbound session', function () {
    ReadonlyServer::tool(SendFeedback::class, [
        'category' => 'difficulty',
        'summary' => 'The schema is confusing',
        'body' => 'I could not tell which ids were required.',
    ])->assertOk();

    expect(ToolFeedback::sole()->agent_id)->toBeNull();
});

it('records the bound agent on a baseline when the agent belongs to the plan project', function () {
    app(AgentContext::class)->set($this->agent);
    $plan = ProjectPlan::create(['project_id' => $this->project->id]);

    $baseline = app(PlanBaseliner::class)->baseline($plan, $this->user);

    expect($baseline->baselined_by_agent_id)->toBe($this->agent->id);
});

it('records a null agent id on a baseline when the bound agent belongs to another project', function () {
    $otherProject = Project::create([
        'workspace_id' => $this->user->active_workspace_id,
        'name' => 'Other',
        'rigor_level' => 2,
    ]);
    $otherAgent = Agent::create([
        'project_id' => $otherProject->id,
        'name' => 'stranger',
        'kind' => 'coding',
    ]);
    app(AgentContext::class)->set($otherAgent);
    $plan = ProjectPlan::create(['project_id' => $this->project->id]);

    $baseline = app(PlanBaseliner::class)->baseline($plan, $this->user);

    expect($baseline->baselined_by_agent_id)->toBeNull();
});

it('records a null agent id on a baseline for an unbound session', function () {
    $plan = ProjectPlan::create(['project_id' => $this->project->id]);

    $baseline = app(PlanBaseliner::class)->baseline($plan, $this->user);

    expect($baseline->baselined_by_agent_id)->toBeNull();
});
