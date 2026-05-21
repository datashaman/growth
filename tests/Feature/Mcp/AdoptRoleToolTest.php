<?php

use App\Mcp\Servers\AllServer;
use App\Mcp\Tools\Common\AdoptRole;
use App\Models\Agent;
use App\Models\McpSession;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use App\Support\AgentContext;
use App\Support\Capability;
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
        'persona' => 'Own the architecture. Confirm with your user before deleting anything.',
    ]);
    $this->role->syncCapabilities([Capability::ManageArchitecture, Capability::ViewDashboard]);

    $this->callAdoptRole = fn (string $roleId, string $sessionId) => $this->postJson('/mcp/all', [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/call',
        'params' => [
            'name' => 'adopt-role',
            'arguments' => ['role_id' => $roleId],
        ],
    ], ['MCP-Session-Id' => $sessionId]);
});

it('adopts a role the user is assigned to and returns its persona', function () {
    $this->user->roles()->attach($this->role->id);

    $response = ($this->callAdoptRole)($this->role->id, 'sess-adopt-ok');

    $response->assertOk();

    expect($response->json('result.structuredContent.role_id'))->toBe($this->role->id)
        ->and($response->json('result.structuredContent.name'))->toBe('Engineering Lead')
        ->and($response->json('result.structuredContent.persona'))
        ->toBe('Own the architecture. Confirm with your user before deleting anything.')
        ->and($response->json('result.structuredContent.capabilities'))
        ->toEqualCanonicalizing(['manage_architecture', 'view_dashboard']);

    $this->assertDatabaseHas('mcp_sessions', [
        'mcp_session_id' => 'sess-adopt-ok',
        'user_id' => $this->user->id,
        'role_id' => $this->role->id,
    ]);
});

it('returns a null persona for a role that carries none', function () {
    $bare = Role::create(['project_id' => $this->project->id, 'name' => 'Reviewer']);
    $this->user->roles()->attach($bare->id);

    $response = ($this->callAdoptRole)($bare->id, 'sess-no-persona');

    $response->assertOk();

    expect($response->json('result.structuredContent.persona'))->toBeNull();
});

it('rejects adoption of a role the user is not assigned to', function () {
    AllServer::actingAs($this->user)
        ->tool(AdoptRole::class, ['role_id' => $this->role->id])
        ->assertHasErrors();

    expect(McpSession::count())->toBe(0);
});

it('checks the bound agent\'s assignment, not the user\'s', function () {
    // The user holds the role, but the session is bound to an agent that does
    // not — the agent is the effective principal, so adoption must fail.
    $this->user->roles()->attach($this->role->id);

    $agent = Agent::create(['project_id' => $this->project->id, 'name' => 'pm-bot']);
    app(AgentContext::class)->set($agent);

    AllServer::actingAs($this->user)
        ->tool(AdoptRole::class, ['role_id' => $this->role->id])
        ->assertHasErrors();
});
