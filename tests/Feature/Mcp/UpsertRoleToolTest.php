<?php

use App\Mcp\Servers\PlanningServer;
use App\Mcp\Tools\Plan\UpsertRole;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use Laravel\Passport\Passport;

beforeEach(function () {
    $this->user = User::factory()->create();
    Passport::actingAs($this->user, ['mcp:use']);

    $this->project = Project::create([
        'workspace_id' => $this->user->active_workspace_id,
        'name' => 'Lunar Lander',
        'rigor_level' => 2,
    ]);
});

it('persists a persona when creating a role', function () {
    PlanningServer::tool(UpsertRole::class, [
        'project_id' => $this->project->id,
        'name' => 'Engineering Lead',
        'persona' => 'Own the architecture.',
    ])->assertOk();

    expect(Role::sole()->persona)->toBe('Own the architecture.');
});

it('updates the persona on an existing role', function () {
    $role = Role::create([
        'project_id' => $this->project->id,
        'name' => 'Engineering Lead',
        'persona' => 'Old persona.',
    ]);

    PlanningServer::tool(UpsertRole::class, [
        'id' => $role->id,
        'project_id' => $this->project->id,
        'name' => 'Engineering Lead',
        'persona' => 'Revised persona.',
    ])->assertOk();

    expect($role->fresh()->persona)->toBe('Revised persona.');
});
