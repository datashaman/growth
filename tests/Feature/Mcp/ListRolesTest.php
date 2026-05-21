<?php

use App\Mcp\Servers\PlanningServer;
use App\Mcp\Tools\Plan\ListRoles;
use App\Models\Agent;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use App\Support\Capability;
use Laravel\Passport\Passport;

beforeEach(function () {
    $this->actor = User::factory()->create();
    Passport::actingAs($this->actor, ['mcp:use']);

    $this->project = Project::create([
        'workspace_id' => $this->actor->active_workspace_id,
        'name' => 'Lunar Lander',
        'rigor_level' => 2,
    ]);
});

it('returns the users and agents assigned to each role', function () {
    $jennifer = User::factory()->create(['name' => 'Jennifer Walker']);
    $agent = Agent::create([
        'project_id' => $this->project->id,
        'name' => 'Scout',
        'kind' => 'assistant',
    ]);
    $role = Role::create(['project_id' => $this->project->id, 'name' => 'Product Lead']);
    $role->syncCapabilities([Capability::ManageIntent, Capability::ViewDashboard]);
    $role->users()->attach($jennifer);
    $role->agents()->attach($agent);

    PlanningServer::tool(ListRoles::class, ['project_id' => $this->project->id])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('results.0.name', 'Product Lead')
            ->where('results.0.capabilities', ['manage_intent', 'view_dashboard'])
            ->where('results.0.users.0.id', $jennifer->id)
            ->where('results.0.users.0.name', 'Jennifer Walker')
            ->where('results.0.agents.0.id', $agent->id)
            ->where('results.0.agents.0.name', 'Scout')
            ->etc());
});

it('returns empty users and agents for an unassigned role', function () {
    Role::create(['project_id' => $this->project->id, 'name' => 'Unfilled Role']);

    PlanningServer::tool(ListRoles::class, ['project_id' => $this->project->id])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('results.0.users', [])
            ->where('results.0.agents', [])
            ->etc());
});
