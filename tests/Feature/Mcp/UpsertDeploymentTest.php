<?php

use App\Mcp\Servers\PlanningServer;
use App\Mcp\Tools\Plan\UpsertDeployment;
use App\Models\Deployment;
use App\Models\Project;
use App\Models\User;
use Laravel\Passport\Passport;

beforeEach(function () {
    $this->user = User::factory()->create();
    Passport::actingAs($this->user, ['mcp:use']);

    $this->project = Project::create([
        'workspace_id' => $this->user->active_workspace_id,
        'name' => 'Deploys',
        'rigor_level' => 2,
    ]);
});

it('accepts a GitHub deployment_status payload from the sync action', function () {
    $args = [
        'project_id' => $this->project->id,
        'environment' => 'production',
        'status' => 'succeeded',
        'provider' => 'github',
        'external_ref' => '999',
        'url' => 'https://app.example.com',
    ];

    PlanningServer::tool(UpsertDeployment::class, $args)
        ->assertOk()
        ->assertStructuredContent(function ($json) {
            $json->where('environment', 'production')
                ->where('status', 'succeeded')
                ->where('created', true)
                ->etc();
        });

    expect(Deployment::where('project_id', $this->project->id)->count())->toBe(1);
});

it('upserts the same provider deployment across repeated status events', function () {
    $args = [
        'project_id' => $this->project->id,
        'environment' => 'production',
        'status' => 'failed',
        'provider' => 'github',
        'external_ref' => '999',
    ];

    PlanningServer::tool(UpsertDeployment::class, $args)->assertOk();
    PlanningServer::tool(UpsertDeployment::class, [...$args, 'status' => 'succeeded'])
        ->assertOk()
        ->assertStructuredContent(function ($json) {
            $json->where('status', 'succeeded')
                ->where('created', false)
                ->etc();
        });

    expect(Deployment::where('project_id', $this->project->id)->count())->toBe(1);
});
