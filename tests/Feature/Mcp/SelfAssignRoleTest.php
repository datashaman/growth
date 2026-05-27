<?php

use App\Mcp\Servers\PlanningServer;
use App\Mcp\Tools\Common\WhoAmI;
use App\Mcp\Tools\Plan\AssignRole;
use App\Mcp\Tools\Plan\UnassignRole;
use App\Models\Agent;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use Laravel\Passport\Passport;

it('lets an unbound MCP user self-assign a role using their who-am-i user id', function () {
    $user = User::factory()->create();
    Passport::actingAs($user, ['mcp:use']);

    $project = Project::create([
        'workspace_id' => $user->active_workspace_id,
        'name' => 'Lunar Lander',
        'rigor_level' => 2,
    ]);
    $role = Role::create(['project_id' => $project->id, 'name' => 'Thermal Lead']);

    // who-am-i is the only identity surface; it must hand back the user id.
    PlanningServer::tool(WhoAmI::class, [])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('user_id', $user->id)
            ->where('roles', [])
            ->etc());

    // That same value must be accepted by assign-role as assignee_id.
    PlanningServer::tool(AssignRole::class, [
        'role_id' => $role->id,
        'assignee_type' => 'user',
        'assignee_id' => $user->id,
    ])->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('role_id', $role->id)
            ->where('assignee_type', 'user')
            ->where('attached', true)
            ->etc());

    expect($role->users()->whereKey($user->id)->exists())->toBeTrue();
});

it('accepts the integer user id over the JSON-RPC wire path', function () {
    $user = User::factory()->create();
    Passport::actingAs($user, ['mcp:use']);

    $project = Project::create([
        'workspace_id' => $user->active_workspace_id,
        'name' => 'Lunar Lander',
        'rigor_level' => 2,
    ]);
    $role = Role::create(['project_id' => $project->id, 'name' => 'Thermal Lead']);

    // A real MCP client posts who-am-i's integer user_id as assignee_id;
    // confirm the schema does not reject it before the handler runs.
    $response = $this->postJson('/mcp/planning', [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/call',
        'params' => [
            'name' => 'assign-role',
            'arguments' => [
                'role_id' => $role->id,
                'assignee_type' => 'user',
                'assignee_id' => $user->id,
            ],
        ],
    ])->assertOk();

    expect($response->json('result.isError'))->not->toBe(true)
        ->and($role->users()->whereKey($user->id)->exists())->toBeTrue();
});

it('detaches a self-assigned role through unassign-role with the user id', function () {
    $user = User::factory()->create();
    Passport::actingAs($user, ['mcp:use']);

    $project = Project::create([
        'workspace_id' => $user->active_workspace_id,
        'name' => 'Lunar Lander',
        'rigor_level' => 2,
    ]);
    $role = Role::create(['project_id' => $project->id, 'name' => 'Thermal Lead']);
    $user->roles()->attach($role->id);

    PlanningServer::tool(UnassignRole::class, [
        'role_id' => $role->id,
        'assignee_type' => 'user',
        'assignee_id' => $user->id,
    ])->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('detached', true)
            ->etc());

    expect($role->users()->whereKey($user->id)->exists())->toBeFalse();
});

it('batch assigns one assignee to many roles and reports idempotent repeats', function () {
    $user = User::factory()->create();
    Passport::actingAs($user, ['mcp:use']);

    $project = Project::create([
        'workspace_id' => $user->active_workspace_id,
        'name' => 'Lunar Lander',
        'rigor_level' => 2,
    ]);
    $thermal = Role::create(['project_id' => $project->id, 'name' => 'Thermal Lead']);
    $guidance = Role::create(['project_id' => $project->id, 'name' => 'Guidance Lead']);

    PlanningServer::tool(AssignRole::class, [
        'role_ids' => [$thermal->id, $guidance->id],
        'assignee_type' => 'user',
        'assignee_id' => $user->id,
    ])->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('results.0.status', 'attached')
            ->where('results.1.status', 'attached')
            ->etc());

    PlanningServer::tool(AssignRole::class, [
        'role_ids' => [$thermal->id, $guidance->id],
        'assignee_type' => 'user',
        'assignee_id' => $user->id,
    ])->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('results.0.status', 'already_assigned')
            ->where('results.0.attached', false)
            ->where('results.1.status', 'already_assigned')
            ->where('results.1.attached', false)
            ->etc());

    expect($thermal->users()->whereKey($user->id)->exists())->toBeTrue()
        ->and($guidance->users()->whereKey($user->id)->exists())->toBeTrue();
});

it('batch assigns mixed explicit user and agent pairs without aborting bad pairs', function () {
    $user = User::factory()->create();
    Passport::actingAs($user, ['mcp:use']);

    $project = Project::create([
        'workspace_id' => $user->active_workspace_id,
        'name' => 'Lunar Lander',
        'rigor_level' => 2,
    ]);
    $thermal = Role::create(['project_id' => $project->id, 'name' => 'Thermal Lead']);
    $software = Role::create(['project_id' => $project->id, 'name' => 'Software Lead']);
    $agent = Agent::create([
        'project_id' => $project->id,
        'name' => 'Build Agent',
        'kind' => 'automation',
    ]);

    PlanningServer::tool(AssignRole::class, [
        'pairs' => [
            ['role_id' => $thermal->id, 'assignee_type' => 'user', 'assignee_id' => $user->id],
            ['role_id' => $software->id, 'assignee_type' => 'agent', 'assignee_id' => $agent->id],
            ['role_id' => 'missing-role', 'assignee_type' => 'user', 'assignee_id' => $user->id],
        ],
    ])->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('results.0.ok', true)
            ->where('results.0.status', 'attached')
            ->where('results.1.ok', true)
            ->where('results.1.status', 'attached')
            ->where('results.2.ok', false)
            ->where('results.2.status', 'error')
            ->etc());

    expect($thermal->users()->whereKey($user->id)->exists())->toBeTrue()
        ->and($software->agents()->whereKey($agent->id)->exists())->toBeTrue();
});

it('batch unassigns independently and reports not-assigned pairs', function () {
    $user = User::factory()->create();
    Passport::actingAs($user, ['mcp:use']);

    $project = Project::create([
        'workspace_id' => $user->active_workspace_id,
        'name' => 'Lunar Lander',
        'rigor_level' => 2,
    ]);
    $thermal = Role::create(['project_id' => $project->id, 'name' => 'Thermal Lead']);
    $guidance = Role::create(['project_id' => $project->id, 'name' => 'Guidance Lead']);
    $agent = Agent::create([
        'project_id' => $project->id,
        'name' => 'Build Agent',
        'kind' => 'automation',
    ]);
    $thermal->users()->attach($user->id);
    $thermal->agents()->attach($agent->id);

    PlanningServer::tool(UnassignRole::class, [
        'pairs' => [
            ['role_id' => $thermal->id, 'assignee_type' => 'user', 'assignee_id' => $user->id],
            ['role_id' => $guidance->id, 'assignee_type' => 'user', 'assignee_id' => $user->id],
            ['role_id' => $thermal->id, 'assignee_type' => 'agent', 'assignee_id' => $agent->id],
        ],
    ])->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('results.0.status', 'detached')
            ->where('results.0.detached', true)
            ->where('results.1.status', 'not_assigned')
            ->where('results.1.detached', false)
            ->where('results.2.status', 'detached')
            ->where('results.2.detached', true)
            ->etc());

    expect($thermal->users()->whereKey($user->id)->exists())->toBeFalse()
        ->and($thermal->agents()->whereKey($agent->id)->exists())->toBeFalse();
});
