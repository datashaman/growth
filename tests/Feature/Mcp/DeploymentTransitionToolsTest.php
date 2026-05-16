<?php

use App\Mcp\Servers\PlanningServer;
use App\Mcp\Tools\Plan\CancelDeployment;
use App\Mcp\Tools\Plan\MarkDeploymentFailed;
use App\Mcp\Tools\Plan\MarkDeploymentSucceeded;
use App\Mcp\Tools\Plan\RollBackDeployment;
use App\Mcp\Tools\Plan\StartDeployment;
use App\Mcp\Tools\Plan\UpsertDeployment;
use App\Models\Deployment;
use App\Models\Project;
use App\Models\StatusTransition;
use App\Models\User;
use Laravel\Passport\Passport;

beforeEach(function () {
    $this->user = User::factory()->create();
    Passport::actingAs($this->user, ['mcp:use']);

    $this->project = Project::create([
        'workspace_id' => $this->user->active_workspace_id,
        'name' => 'Transitions',
        'rigor_level' => 2,
    ]);

    $this->makeDeployment = fn (string $status): Deployment => Deployment::create([
        'project_id' => $this->project->id,
        'environment' => 'production',
        'status' => $status,
    ]);
});

it('starts a planned deployment and records a transition', function () {
    $deployment = ($this->makeDeployment)('planned');

    PlanningServer::tool(StartDeployment::class, ['deployment_id' => $deployment->id])
        ->assertOk()
        ->assertStructuredContent(function ($json) use ($deployment) {
            $json->where('deployment_id', $deployment->id)
                ->where('from_status', 'planned')
                ->where('to_status', 'in_progress')
                ->etc();
        });

    expect($deployment->fresh()->status)->toBe('in_progress');

    $transition = StatusTransition::query()->sole();
    expect($transition->to_status)->toBe('in_progress')
        ->and($transition->transitioned_by_user_id)->toBe($this->user->id)
        ->and($transition->transitionable->is($deployment))->toBeTrue();
});

it('rejects starting a deployment that is not planned', function () {
    $deployment = ($this->makeDeployment)('succeeded');

    PlanningServer::tool(StartDeployment::class, ['deployment_id' => $deployment->id])
        ->assertHasErrors(['Cannot start a deployment that is succeeded.']);

    expect($deployment->fresh()->status)->toBe('succeeded');
    expect(StatusTransition::count())->toBe(0);
});

it('marks an in_progress deployment as succeeded', function () {
    $deployment = ($this->makeDeployment)('in_progress');

    PlanningServer::tool(MarkDeploymentSucceeded::class, ['deployment_id' => $deployment->id])
        ->assertOk()
        ->assertStructuredContent(function ($json) {
            $json->where('to_status', 'succeeded')->etc();
        });

    expect($deployment->fresh()->status)->toBe('succeeded');
});

it('marks an in_progress deployment as failed', function () {
    $deployment = ($this->makeDeployment)('in_progress');

    PlanningServer::tool(MarkDeploymentFailed::class, ['deployment_id' => $deployment->id])
        ->assertOk()
        ->assertStructuredContent(function ($json) {
            $json->where('to_status', 'failed')->etc();
        });

    expect($deployment->fresh()->status)->toBe('failed');
});

it('rejects marking a planned deployment succeeded', function () {
    $deployment = ($this->makeDeployment)('planned');

    PlanningServer::tool(MarkDeploymentSucceeded::class, ['deployment_id' => $deployment->id])
        ->assertHasErrors(['Cannot mark succeeded a deployment that is planned.']);

    expect($deployment->fresh()->status)->toBe('planned');
});

it('rolls back a succeeded deployment', function () {
    $deployment = ($this->makeDeployment)('succeeded');

    PlanningServer::tool(RollBackDeployment::class, ['deployment_id' => $deployment->id])
        ->assertOk()
        ->assertStructuredContent(function ($json) {
            $json->where('to_status', 'rolled_back')->etc();
        });

    expect($deployment->fresh()->status)->toBe('rolled_back');
});

it('rolls back a failed deployment', function () {
    $deployment = ($this->makeDeployment)('failed');

    PlanningServer::tool(RollBackDeployment::class, ['deployment_id' => $deployment->id])
        ->assertOk();

    expect($deployment->fresh()->status)->toBe('rolled_back');
});

it('rejects rolling back a planned deployment', function () {
    $deployment = ($this->makeDeployment)('planned');

    PlanningServer::tool(RollBackDeployment::class, ['deployment_id' => $deployment->id])
        ->assertHasErrors(['Cannot roll back a deployment that is planned.']);

    expect($deployment->fresh()->status)->toBe('planned');
});

it('cancels a planned deployment', function () {
    $deployment = ($this->makeDeployment)('planned');

    PlanningServer::tool(CancelDeployment::class, ['deployment_id' => $deployment->id])
        ->assertOk();

    expect($deployment->fresh()->status)->toBe('cancelled');
});

it('rejects cancelling an in_progress deployment', function () {
    $deployment = ($this->makeDeployment)('in_progress');

    PlanningServer::tool(CancelDeployment::class, ['deployment_id' => $deployment->id])
        ->assertHasErrors(['Cannot cancel a deployment that is in progress.']);

    expect($deployment->fresh()->status)->toBe('in_progress');
});

it('still accepts a raw status on upsert-deployment so the sync path is unchanged', function () {
    PlanningServer::tool(UpsertDeployment::class, [
        'project_id' => $this->project->id,
        'environment' => 'staging',
        'status' => 'succeeded',
    ])
        ->assertOk()
        ->assertStructuredContent(function ($json) {
            $json->where('status', 'succeeded')->etc();
        });

    expect(Deployment::where('environment', 'staging')->sole()->status)->toBe('succeeded');
});

it('rejects a transition on a deployment the user does not own', function () {
    $stranger = User::factory()->create();
    $strangerProject = Project::create([
        'workspace_id' => $stranger->active_workspace_id,
        'name' => 'Foreign',
        'rigor_level' => 1,
    ]);
    $foreignDeployment = Deployment::create([
        'project_id' => $strangerProject->id,
        'environment' => 'production',
        'status' => 'planned',
    ]);

    PlanningServer::tool(StartDeployment::class, ['deployment_id' => $foreignDeployment->id])
        ->assertHasErrors();

    expect($foreignDeployment->fresh()->status)->toBe('planned');
});
