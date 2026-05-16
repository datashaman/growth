<?php

use App\Mcp\Servers\ManagementServer;
use App\Mcp\Tools\Projects\ResolveProjectByRepo;
use App\Models\Project;
use App\Models\User;
use Laravel\Passport\Passport;

beforeEach(function () {
    $this->user = User::factory()->create();
    Passport::actingAs($this->user, ['mcp:use']);
});

it('resolves a bound repo to its project', function () {
    $project = Project::create([
        'workspace_id' => $this->user->active_workspace_id,
        'name' => 'Growth',
        'rigor_level' => 2,
        'github_repo' => 'datashaman/growth',
    ]);

    ManagementServer::tool(ResolveProjectByRepo::class, ['github_repo' => 'datashaman/growth'])
        ->assertOk()
        ->assertStructuredContent(function ($json) use ($project) {
            $json->where('found', true)
                ->where('project_id', $project->id)
                ->where('github_repo', 'datashaman/growth')
                ->where('name', 'Growth')
                ->where('status', 'active');
        });
});

it('reports not found for an unbound repo without erroring', function () {
    ManagementServer::tool(ResolveProjectByRepo::class, ['github_repo' => 'datashaman/unknown'])
        ->assertOk()
        ->assertStructuredContent(function ($json) {
            $json->where('found', false)
                ->where('github_repo', 'datashaman/unknown')
                ->where('project_id', null)
                ->where('name', null)
                ->where('status', null);
        });
});

it('rejects a malformed repo argument', function () {
    ManagementServer::tool(ResolveProjectByRepo::class, ['github_repo' => 'not-a-repo'])
        ->assertHasErrors();
});

it('does not resolve a repo bound in another workspace', function () {
    $other = User::factory()->create();
    Project::create([
        'workspace_id' => $other->active_workspace_id,
        'name' => 'Foreign',
        'rigor_level' => 2,
        'github_repo' => 'datashaman/foreign',
    ]);

    ManagementServer::tool(ResolveProjectByRepo::class, ['github_repo' => 'datashaman/foreign'])
        ->assertOk()
        ->assertStructuredContent(function ($json) {
            $json->where('found', false)
                ->where('project_id', null)
                ->etc();
        });
});
