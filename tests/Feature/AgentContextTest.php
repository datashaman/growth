<?php

use App\Models\Agent;
use App\Models\Project;
use App\Models\User;
use App\Support\AgentContext;
use Laravel\Passport\AccessToken;
use Laravel\Passport\Passport;

/**
 * Create an agent under a fresh project in the user's active workspace.
 */
function agentFor(User $user, string $name = 'Verifier'): Agent
{
    $project = Project::create([
        'workspace_id' => $user->active_workspace_id,
        'name' => 'Agent Context '.$name,
        'rigor_level' => 2,
    ]);

    return Agent::create([
        'project_id' => $project->id,
        'name' => $name,
        'kind' => 'coding',
    ]);
}

it('resolves the agent from an agent-bound access token', function () {
    $user = User::factory()->create();
    Passport::actingAs($user, ['mcp:use']);
    $agent = agentFor($user);
    $user->withAccessToken(new AccessToken([
        'oauth_scopes' => ['mcp:use'],
        'agent_id' => $agent->id,
    ]));

    $context = app(AgentContext::class);

    expect($context->id())->toBe($agent->id)
        ->and($context->source())->toBe('token');
});

it('resolves the agent from the GROWTH_AGENT_ID env for a local session', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $agent = agentFor($user);

    $_SERVER['GROWTH_AGENT_ID'] = $agent->id;

    try {
        $context = app(AgentContext::class);

        expect($context->id())->toBe($agent->id)
            ->and($context->source())->toBe('env');
    } finally {
        unset($_SERVER['GROWTH_AGENT_ID']);
    }
});

it('ignores the GROWTH_AGENT_ID env when no user is authenticated', function () {
    $_SERVER['GROWTH_AGENT_ID'] = 'some-agent-id';

    try {
        expect(app(AgentContext::class)->id())->toBeNull();
    } finally {
        unset($_SERVER['GROWTH_AGENT_ID']);
    }
});

it('leaves the session unbound when no agent is bound', function () {
    $user = User::factory()->create();
    Passport::actingAs($user, ['mcp:use']);

    $context = app(AgentContext::class);

    expect($context->id())->toBeNull()
        ->and($context->agent())->toBeNull()
        ->and($context->source())->toBeNull();
});

it('has no requireId — an unbound agent is a valid state, not an error', function () {
    expect(method_exists(AgentContext::class, 'requireId'))->toBeFalse();
});

it('attributes a call only when the agent belongs to the call project', function () {
    $user = User::factory()->create();
    Passport::actingAs($user, ['mcp:use']);
    $agent = agentFor($user);
    $user->withAccessToken(new AccessToken([
        'oauth_scopes' => ['mcp:use'],
        'agent_id' => $agent->id,
    ]));

    $otherProject = Project::create([
        'workspace_id' => $user->active_workspace_id,
        'name' => 'A different project',
        'rigor_level' => 2,
    ]);

    $context = app(AgentContext::class);

    expect($context->idForProject($agent->project_id))->toBe($agent->id)
        ->and($context->idForProject($otherProject->id))->toBeNull()
        ->and($context->idForProject(null))->toBeNull();
});

it('resolves to null for a stale agent id that names no agent', function () {
    $user = User::factory()->create();
    Passport::actingAs($user, ['mcp:use']);
    $user->withAccessToken(new AccessToken([
        'oauth_scopes' => ['mcp:use'],
        'agent_id' => 'stale-missing-agent-id',
    ]));

    $context = app(AgentContext::class);

    expect($context->agent())->toBeNull()
        ->and($context->id())->toBeNull();
});

it('resolves to null for an agent in another workspace', function () {
    $foreignAgent = agentFor(User::factory()->create());

    $user = User::factory()->create();
    Passport::actingAs($user, ['mcp:use']);
    $user->withAccessToken(new AccessToken([
        'oauth_scopes' => ['mcp:use'],
        'agent_id' => $foreignAgent->id,
    ]));

    expect(app(AgentContext::class)->agent())->toBeNull();
});

it('honours a set() override and forget() clears it', function () {
    $user = User::factory()->create();
    Passport::actingAs($user, ['mcp:use']);
    $agent = agentFor($user);

    $context = app(AgentContext::class);
    $context->set($agent);
    expect($context->id())->toBe($agent->id)
        ->and($context->source())->toBe('override');

    $context->forget();
    expect($context->id())->toBeNull();
});
