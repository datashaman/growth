<?php

use App\Growth\Transitions\StartAnomalyInvestigation;
use App\Mcp\Servers\GovernanceServer;
use App\Mcp\Servers\ReadonlyServer;
use App\Mcp\Tools\Common\WhoAmI;
use App\Mcp\Tools\Feedback\CommentFeedback;
use App\Mcp\Tools\Feedback\GetFeedback;
use App\Mcp\Tools\Feedback\ListToolInvocations;
use App\Models\Anomaly;
use App\Models\FeedbackComment;
use App\Models\Project;
use App\Models\Role;
use App\Models\StatusTransition;
use App\Models\ToolFeedback;
use App\Models\ToolInvocation;
use App\Models\User;
use App\Support\RoleContext;
use Laravel\Passport\Passport;

beforeEach(function () {
    $this->user = User::factory()->create();
    Passport::actingAs($this->user, ['mcp:use']);

    $this->project = Project::create([
        'workspace_id' => $this->user->active_workspace_id,
        'name' => 'Lunar Lander',
        'rigor_level' => 2,
    ]);

    $this->role = Role::create([
        'project_id' => $this->project->id,
        'name' => 'Engineering Lead',
    ]);
});

it('stamps the adopted role onto a tool invocation', function () {
    app(RoleContext::class)->set($this->role);

    ReadonlyServer::tool(WhoAmI::class)->assertOk();

    $invocation = ToolInvocation::sole();

    expect($invocation->acting_role_id)->toBe($this->role->id)
        ->and($invocation->acting_role_name)->toBe('Engineering Lead');
});

it('leaves the acting role null on a tool invocation for an unbound session', function () {
    ReadonlyServer::tool(WhoAmI::class)->assertOk();

    $invocation = ToolInvocation::sole();

    expect($invocation->acting_role_id)->toBeNull()
        ->and($invocation->acting_role_name)->toBeNull();
});

it('stamps the adopted role onto a status transition audit row', function () {
    $anomaly = Anomaly::create([
        'project_id' => $this->project->id,
        'severity' => 'high',
        'status' => 'open',
        'summary' => 'Checkout fails',
        'description' => 'The cart total is wrong.',
    ]);

    app(RoleContext::class)->set($this->role);

    $transition = (new StartAnomalyInvestigation)->apply($anomaly, $this->user, 'Triaging');

    expect($transition->acting_role_id)->toBe($this->role->id)
        ->and($transition->acting_role_name)->toBe('Engineering Lead')
        ->and(StatusTransition::sole()->acting_role_name)->toBe('Engineering Lead');
});

it('keeps the frozen role name on the audit row after the role is deleted', function () {
    app(RoleContext::class)->set($this->role);

    ReadonlyServer::tool(WhoAmI::class)->assertOk();

    $this->role->delete();

    $invocation = ToolInvocation::sole()->fresh();

    expect($invocation->acting_role_id)->toBeNull()
        ->and($invocation->acting_role_name)->toBe('Engineering Lead');
});

it('stamps the adopted role onto a feedback comment', function () {
    $feedback = ToolFeedback::create([
        'workspace_id' => $this->user->active_workspace_id,
        'category' => 'difficulty',
        'status' => 'new',
        'summary' => 'Generic summary',
        'body' => 'Generic body',
    ]);

    app(RoleContext::class)->set($this->role);

    GovernanceServer::tool(CommentFeedback::class, ['feedback_id' => $feedback->id, 'body' => 'Triage note'])
        ->assertOk();

    $comment = $feedback->comments()->sole();

    expect($comment->acting_role_id)->toBe($this->role->id)
        ->and($comment->acting_role_name)->toBe('Engineering Lead');
});

it('returns the acting role through the list-tool-invocations MCP tool', function () {
    ToolInvocation::create([
        'workspace_id' => $this->user->active_workspace_id,
        'user_id' => $this->user->id,
        'acting_role_id' => $this->role->id,
        'acting_role_name' => 'Engineering Lead',
        'tool_name' => 'role-call',
        'transport' => 'http',
        'success' => true,
        'duration_ms' => 5,
        'started_at' => now(),
        'completed_at' => now(),
    ]);

    ReadonlyServer::tool(ListToolInvocations::class, ['tool_name' => 'role-call'])
        ->assertOk()
        ->assertStructuredContent(function ($json) {
            $json->where('results.0.acting_role_name', 'Engineering Lead')
                ->where('results.0.acting_role_id', $this->role->id)
                ->etc();
        });
});

it('returns the acting role on feedback comments through get-feedback', function () {
    $feedback = ToolFeedback::create([
        'workspace_id' => $this->user->active_workspace_id,
        'category' => 'difficulty',
        'status' => 'new',
        'summary' => 'Generic summary',
        'body' => 'Generic body',
    ]);
    FeedbackComment::create([
        'tool_feedback_id' => $feedback->id,
        'user_id' => $this->user->id,
        'acting_role_id' => $this->role->id,
        'acting_role_name' => 'Engineering Lead',
        'body' => 'Triage note',
    ]);

    ReadonlyServer::tool(GetFeedback::class, ['feedback_id' => $feedback->id])
        ->assertOk()
        ->assertStructuredContent(function ($json) {
            $json->where('comments.0.acting_role_name', 'Engineering Lead')
                ->where('comments.0.acting_role_id', $this->role->id)
                ->etc();
        });
});
