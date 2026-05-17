<?php

use App\Mcp\Servers\ManagementServer;
use App\Mcp\Tools\Projects\MoveProject;
use App\Models\Project;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMembership;
use Laravel\Passport\Passport;

beforeEach(function () {
    $this->user = User::factory()->create();
    Passport::actingAs($this->user, ['mcp:use']);

    $this->project = Project::create([
        'workspace_id' => $this->user->active_workspace_id,
        'name' => 'Mover',
        'rigor_level' => 2,
    ]);

    // A workspace the user belongs to with the given role.
    $this->destination = function (string $role = WorkspaceMembership::ROLE_OWNER): Workspace {
        $workspace = Workspace::create([
            'name' => 'Dest '.uniqid(),
            'slug' => Workspace::uniqueSlug('dest-'.uniqid()),
            'owner_user_id' => $role === WorkspaceMembership::ROLE_OWNER ? $this->user->id : null,
        ]);

        WorkspaceMembership::create([
            'workspace_id' => $workspace->id,
            'user_id' => $this->user->id,
            'role' => $role,
            'last_accessed_at' => now()->subDay(),
        ]);

        return $workspace;
    };
});

it('moves a project to a workspace where the caller is an owner', function () {
    $destination = ($this->destination)();

    ManagementServer::tool(MoveProject::class, [
        'id' => $this->project->id,
        'destination_workspace_id' => $destination->id,
    ])
        ->assertOk()
        ->assertStructuredContent(function ($json) use ($destination) {
            $json->where('moved', true)
                ->where('workspace_id', $destination->id)
                ->etc();
        });

    expect(Project::withoutGlobalScope('workspace')->find($this->project->id)->workspace_id)
        ->toBe($destination->id);
});

it('rejects a move to a workspace where the caller is only a viewer', function () {
    $destination = ($this->destination)(WorkspaceMembership::ROLE_VIEWER);

    ManagementServer::tool(MoveProject::class, [
        'id' => $this->project->id,
        'destination_workspace_id' => $destination->id,
    ])
        ->assertHasErrors(['Cannot move the project: you must be an owner or admin of both the current workspace and the destination workspace.']);

    expect(Project::withoutGlobalScope('workspace')->find($this->project->id)->workspace_id)
        ->toBe($this->user->active_workspace_id);
});

it('rejects a move whose destination equals the current workspace', function () {
    ManagementServer::tool(MoveProject::class, [
        'id' => $this->project->id,
        'destination_workspace_id' => $this->user->active_workspace_id,
    ])
        ->assertHasErrors(['Destination must differ from source.']);
});

it('does not move a project from another workspace', function () {
    $stranger = User::factory()->create();
    $foreign = Project::create([
        'workspace_id' => $stranger->active_workspace_id,
        'name' => 'Off limits',
        'rigor_level' => 2,
    ]);
    $destination = ($this->destination)();

    ManagementServer::tool(MoveProject::class, [
        'id' => $foreign->id,
        'destination_workspace_id' => $destination->id,
    ])->assertHasErrors(['The selected id is invalid.']);

    expect(Project::withoutGlobalScope('workspace')->find($foreign->id)->workspace_id)
        ->toBe($stranger->active_workspace_id);
});
