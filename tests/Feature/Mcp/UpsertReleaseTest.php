<?php

use App\Mcp\Servers\PlanningServer;
use App\Mcp\Tools\Plan\UpsertRelease;
use App\Models\Project;
use App\Models\Release;
use App\Models\User;
use Laravel\Passport\Passport;

beforeEach(function () {
    $this->user = User::factory()->create();
    Passport::actingAs($this->user, ['mcp:use']);

    $this->project = Project::create([
        'workspace_id' => $this->user->active_workspace_id,
        'name' => 'Releases',
        'rigor_level' => 2,
    ]);
});

it('accepts a GitHub release payload from the sync action', function () {
    $args = [
        'project_id' => $this->project->id,
        'version' => 'v1.2.3',
        'name' => 'Spring release',
        'status' => 'released',
        'released_at' => '2026-01-01T00:00:00Z',
    ];

    PlanningServer::tool(UpsertRelease::class, $args)
        ->assertOk()
        ->assertStructuredContent(function ($json) {
            $json->where('version', 'v1.2.3')
                ->where('status', 'released')
                ->where('created', true)
                ->etc();
        });

    expect(Release::where('project_id', $this->project->id)->count())->toBe(1);
});

it('upserts the same release version when republished', function () {
    $args = [
        'project_id' => $this->project->id,
        'version' => 'v1.2.3',
        'name' => 'Spring release',
        'status' => 'released',
    ];

    PlanningServer::tool(UpsertRelease::class, $args)->assertOk();
    PlanningServer::tool(UpsertRelease::class, [...$args, 'name' => 'Spring release (edited)'])
        ->assertOk()
        ->assertStructuredContent(function ($json) {
            $json->where('name', 'Spring release (edited)')
                ->where('created', false)
                ->etc();
        });

    expect(Release::where('project_id', $this->project->id)->count())->toBe(1);
});
