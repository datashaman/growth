<?php

use App\Mcp\Servers\IntakeServer;
use App\Mcp\Tools\WhoAmI;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use Laravel\Passport\Passport;

it('returns zero counts and empty arrays for an authenticated user with no projects', function () {
    Passport::actingAs(User::factory()->create(), ['mcp:use']);

    IntakeServer::tool(WhoAmI::class, [])
        ->assertOk()
        ->assertStructuredContent(function ($json) {
            $json->where('authenticated', true)
                ->where('owned_projects', 0)
                ->where('last_touched_project', null)
                ->where('roles', [])
                ->etc();
        });
});

it('reports owned-project count, last-touched project, and role memberships', function () {
    $user = User::factory()->create();
    Passport::actingAs($user, ['mcp:use']);

    $older = Project::create(['user_id' => $user->id, 'name' => 'Older', 'rigor_level' => 1]);
    $newer = Project::create(['user_id' => $user->id, 'name' => 'Newer', 'rigor_level' => 2]);
    Project::where('id', $newer->id)->update(['updated_at' => now()->addMinute()]);

    $role = Role::create(['project_id' => $older->id, 'name' => 'QA Lead']);
    $user->roles()->attach($role->id);

    IntakeServer::tool(WhoAmI::class, [])
        ->assertOk()
        ->assertStructuredContent(function ($json) use ($newer, $older) {
            $json->where('owned_projects', 2)
                ->where('last_touched_project.id', $newer->id)
                ->where('last_touched_project.name', 'Newer')
                ->has('roles', 1)
                ->where('roles.0.name', 'QA Lead')
                ->where('roles.0.project_id', $older->id)
                ->where('roles.0.project_name', 'Older')
                ->etc();
        });
});
