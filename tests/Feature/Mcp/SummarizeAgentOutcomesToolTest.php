<?php

use App\Mcp\Servers\ReadonlyServer;
use App\Mcp\Tools\Dashboard\SummarizeAgentOutcomes;
use App\Mcp\Tools\Feedback\ListToolInvocations;
use App\Models\Agent;
use App\Models\Project;
use App\Models\ProjectPlan;
use App\Models\ProjectPlanBaseline;
use App\Models\ToolFeedback;
use App\Models\ToolInvocation;
use App\Models\User;
use Laravel\Passport\Passport;

beforeEach(function () {
    $this->actor = User::factory()->create();
    Passport::actingAs($this->actor, ['mcp:use']);

    $this->workspaceId = $this->actor->active_workspace_id;
    $this->project = Project::create([
        'workspace_id' => $this->workspaceId,
        'name' => 'Festival Market',
        'rigor_level' => 2,
    ]);
    $this->agent = Agent::create([
        'project_id' => $this->project->id,
        'name' => 'Scout',
        'kind' => 'assistant',
    ]);

    $this->invoke = function (array $attributes): ToolInvocation {
        return ToolInvocation::create(array_merge([
            'workspace_id' => $this->workspaceId,
            'agent_id' => $this->agent->id,
            'tool_name' => 'list-projects',
            'transport' => 'http',
            'success' => true,
            'duration_ms' => 100,
            'started_at' => now()->subDay(),
            'completed_at' => now()->subDay(),
        ], $attributes));
    };
});

it('aggregates activity, tool usage, errors and durations for an agent', function () {
    ($this->invoke)(['tool_name' => 'list-projects', 'success' => true, 'duration_ms' => 100]);
    ($this->invoke)(['tool_name' => 'list-projects', 'success' => true, 'duration_ms' => 300]);
    ($this->invoke)(['tool_name' => 'lint-project', 'success' => false, 'error_class' => 'tool_error', 'duration_ms' => 200]);
    ($this->invoke)(['tool_name' => 'reopen-work-item', 'success' => true, 'duration_ms' => 50]);

    ReadonlyServer::tool(SummarizeAgentOutcomes::class, [])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->has('window', fn ($w) => $w->where('window_days', 90)->has('since')->has('note'))
            ->has('agents', 1)
            ->where('agents.0.identity.name', 'Scout')
            ->where('agents.0.activity.total_invocations', 4)
            ->where('agents.0.activity.successes', 3)
            ->where('agents.0.activity.failures', 1)
            ->where('agents.0.activity.success_rate', 0.75)
            ->where('agents.0.activity.corrective_actions', 1)
            ->where('agents.0.tool_usage.list-projects', 2)
            ->where('agents.0.tool_usage.lint-project', 1)
            ->where('agents.0.errors.tool_error', 1)
            ->where('agents.0.durations.average_ms', 163)
            ->where('agents.0.durations.max_ms', 300)
            ->where('agents.0.baselines_authored', 0)
            ->etc());
});

it('counts feedback by category and authored baselines', function () {
    ToolFeedback::create([
        'workspace_id' => $this->workspaceId,
        'agent_id' => $this->agent->id,
        'project_id' => $this->project->id,
        'category' => 'bug',
        'status' => 'new',
        'tool_name' => 'lint-project',
        'summary' => 'Broke', 'body' => 'It broke.',
    ]);
    ToolFeedback::create([
        'workspace_id' => $this->workspaceId,
        'agent_id' => $this->agent->id,
        'project_id' => $this->project->id,
        'category' => 'suggestion',
        'status' => 'new',
        'tool_name' => 'lint-project',
        'summary' => 'Idea', 'body' => 'An idea.',
    ]);

    $plan = ProjectPlan::create(['project_id' => $this->project->id, 'status' => 'draft']);
    ProjectPlanBaseline::create([
        'project_plan_id' => $plan->id,
        'version' => 1,
        'kind' => 'planned',
        'snapshot' => [],
        'baselined_at' => now(),
        'baselined_by_agent_id' => $this->agent->id,
    ]);

    ReadonlyServer::tool(SummarizeAgentOutcomes::class, [])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('agents.0.feedback.total', 2)
            ->where('agents.0.feedback.by_category.bug', 1)
            ->where('agents.0.feedback.by_category.suggestion', 1)
            ->where('agents.0.baselines_authored', 1)
            ->etc());
});

it('includes an agent with no attributed work as an all-zero row', function () {
    ReadonlyServer::tool(SummarizeAgentOutcomes::class, [])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->has('agents', 1)
            ->where('agents.0.activity.total_invocations', 0)
            ->where('agents.0.activity.successes', 0)
            ->where('agents.0.activity.failures', 0)
            ->where('agents.0.activity.success_rate', 0.0)
            ->where('agents.0.activity.corrective_actions', 0)
            ->where('agents.0.durations.average_ms', 0)
            ->where('agents.0.durations.max_ms', 0)
            ->where('agents.0.feedback.total', 0)
            ->where('agents.0.baselines_authored', 0)
            ->etc());
});

it('excludes invocations older than the 90-day prune window', function () {
    ($this->invoke)(['started_at' => now()->subDays(91)]);
    ($this->invoke)(['started_at' => now()->subDay()]);

    ReadonlyServer::tool(SummarizeAgentOutcomes::class, [])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('agents.0.activity.total_invocations', 1)
            ->etc());
});

it('never includes another workspace events in an agent metrics', function () {
    $other = User::factory()->create();
    $otherProject = Project::create([
        'workspace_id' => $other->active_workspace_id,
        'name' => 'Elsewhere',
        'rigor_level' => 2,
    ]);
    $otherAgent = Agent::create([
        'project_id' => $otherProject->id,
        'name' => 'Foreign',
        'kind' => 'assistant',
    ]);

    // Foreign-workspace invocation, feedback and baseline.
    ToolInvocation::create([
        'workspace_id' => $other->active_workspace_id,
        'agent_id' => $otherAgent->id,
        'tool_name' => 'list-projects',
        'transport' => 'http',
        'success' => true,
        'duration_ms' => 100,
        'started_at' => now()->subDay(),
        'completed_at' => now()->subDay(),
    ]);
    ToolFeedback::create([
        'workspace_id' => $other->active_workspace_id,
        'agent_id' => $otherAgent->id,
        'project_id' => $otherProject->id,
        'category' => 'bug',
        'status' => 'new',
        'tool_name' => 'lint-project',
        'summary' => 'Foreign', 'body' => 'Foreign body.',
    ]);
    $otherPlan = ProjectPlan::create(['project_id' => $otherProject->id, 'status' => 'draft']);
    ProjectPlanBaseline::create([
        'project_plan_id' => $otherPlan->id,
        'version' => 1,
        'kind' => 'planned',
        'snapshot' => [],
        'baselined_at' => now(),
        'baselined_by_agent_id' => $otherAgent->id,
    ]);

    // The active workspace's own agent has its own work.
    ($this->invoke)(['tool_name' => 'list-projects']);

    ReadonlyServer::tool(SummarizeAgentOutcomes::class, [])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->has('agents', 1)
            ->where('agents.0.identity.name', 'Scout')
            ->where('agents.0.activity.total_invocations', 1)
            ->etc());
});

it('reproduces the reported activity count via the list-tool-invocations agent_id filter', function () {
    ($this->invoke)(['tool_name' => 'list-projects']);
    ($this->invoke)(['tool_name' => 'lint-project']);
    ($this->invoke)(['tool_name' => 'reopen-work-item']);

    ReadonlyServer::tool(ListToolInvocations::class, ['agent_id' => $this->agent->id])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('total', 3)
            ->etc());
});
