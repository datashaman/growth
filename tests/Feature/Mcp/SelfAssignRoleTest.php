<?php

use App\Mcp\Servers\PlanningServer;
use App\Mcp\Tools\Common\WhoAmI;
use App\Mcp\Tools\Plan\AssignRole;
use App\Mcp\Tools\Plan\UnassignRole;
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
