<?php

use App\Mcp\Servers\ReadonlyServer;
use App\Mcp\Tools\Common\Doctor;
use App\Models\Agent;
use App\Models\Project;
use App\Models\User;
use Laravel\Passport\AccessToken;
use Laravel\Passport\Passport;

// checks[] order is fixed by Doctor::handle():
//   0 authentication, 1 active_workspace, 2 workspace_token_binding,
//   3 mcp_scope, 4 token_expiry, 5 local_session_env, 6 workspace_projects,
//   7 acting_agent

it('reports a clean bill of health for a fully configured HTTP session', function () {
    $user = User::factory()->create();
    Passport::actingAs($user, ['mcp:use']);
    $user->withAccessToken(new AccessToken([
        'oauth_scopes' => ['mcp:use'],
        'workspace_id' => $user->active_workspace_id,
    ]));

    Project::create([
        'workspace_id' => $user->active_workspace_id,
        'name' => 'Doctor project',
        'rigor_level' => 2,
    ]);

    ReadonlyServer::tool(Doctor::class)
        ->assertOk()
        ->assertStructuredContent(function ($json) {
            $json->where('transport', 'http')
                ->where('authenticated', true)
                ->where('overall', 'pass')
                ->where('checks.1.status', 'pass')
                ->where('checks.2.status', 'pass')
                ->where('checks.3.status', 'pass')
                ->where('checks.6.status', 'pass')
                ->etc();
        });
});

it('warns when the token is not workspace-bound and the workspace is empty', function () {
    $user = User::factory()->create();
    Passport::actingAs($user, ['mcp:use']);

    ReadonlyServer::tool(Doctor::class)
        ->assertOk()
        ->assertStructuredContent(function ($json) {
            $json->where('overall', 'warn')
                ->where('checks.2.status', 'warn')
                ->where('checks.6.status', 'warn')
                ->etc();
        });
});

it('fails when the token is missing the mcp:use scope', function () {
    $user = User::factory()->create();
    Passport::actingAs($user, []);

    ReadonlyServer::tool(Doctor::class)
        ->assertOk()
        ->assertStructuredContent(function ($json) {
            $json->where('overall', 'fail')
                ->where('checks.3.status', 'fail')
                ->etc();
        });
});

it('never errors for an unauthenticated session', function () {
    ReadonlyServer::tool(Doctor::class)
        ->assertOk()
        ->assertStructuredContent(function ($json) {
            $json->where('authenticated', false)
                ->where('overall', 'fail')
                ->where('checks.0.status', 'fail')
                ->etc();
        });
});

it('treats token checks as not applicable on a local stdio session', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    ReadonlyServer::tool(Doctor::class)
        ->assertOk()
        ->assertStructuredContent(function ($json) {
            $json->where('transport', 'stdio')
                ->where('checks.2.status', 'not_applicable')
                ->where('checks.3.status', 'not_applicable')
                ->where('checks.4.status', 'not_applicable')
                ->where('checks.5.status', 'pass')
                ->etc();
        });
});

it('fails the workspace check when no workspace is bound', function () {
    $user = User::withoutDefaultWorkspace(fn () => User::factory()->create());
    Passport::actingAs($user, ['mcp:use']);

    ReadonlyServer::tool(Doctor::class)
        ->assertOk()
        ->assertStructuredContent(function ($json) {
            $json->where('overall', 'fail')
                ->where('checks.1.status', 'fail')
                ->where('checks.6.status', 'not_applicable')
                ->etc();
        });
});

it('reports the agent a session is acting as', function () {
    $user = User::factory()->create();
    Passport::actingAs($user, ['mcp:use']);

    $project = Project::create([
        'workspace_id' => $user->active_workspace_id,
        'name' => 'Doctor agent project',
        'rigor_level' => 2,
    ]);
    $agent = Agent::create([
        'project_id' => $project->id,
        'name' => 'Verifier',
        'kind' => 'coding',
    ]);

    $user->withAccessToken(new AccessToken([
        'oauth_scopes' => ['mcp:use'],
        'workspace_id' => $user->active_workspace_id,
        'agent_id' => $agent->id,
    ]));

    ReadonlyServer::tool(Doctor::class)
        ->assertOk()
        ->assertStructuredContent(function ($json) {
            $json->where('overall', 'pass')
                ->where('checks.7.check', 'acting_agent')
                ->where('checks.7.status', 'pass')
                ->etc();
        });
});

it('passes the agent check when no agent is bound', function () {
    $user = User::factory()->create();
    Passport::actingAs($user, ['mcp:use']);

    ReadonlyServer::tool(Doctor::class)
        ->assertOk()
        ->assertStructuredContent(function ($json) {
            $json->where('checks.7.check', 'acting_agent')
                ->where('checks.7.status', 'pass')
                ->etc();
        });
});

it('warns when a bound agent id does not resolve', function () {
    $user = User::factory()->create();
    Passport::actingAs($user, ['mcp:use']);
    $user->withAccessToken(new AccessToken([
        'oauth_scopes' => ['mcp:use'],
        'workspace_id' => $user->active_workspace_id,
        'agent_id' => 'stale-missing-agent-id',
    ]));

    ReadonlyServer::tool(Doctor::class)
        ->assertOk()
        ->assertStructuredContent(function ($json) {
            $json->where('checks.7.status', 'warn')
                ->etc();
        });
});
