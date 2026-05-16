<?php

use App\Mcp\Servers\ManagementServer;
use App\Mcp\Tools\Projects\ActivateProject;
use App\Mcp\Tools\Projects\ArchiveProject;
use App\Mcp\Tools\Projects\CloseProject;
use App\Mcp\Tools\Projects\RestoreProject;
use App\Mcp\Tools\Projects\UpdateProject;
use App\Mcp\Tools\Projects\UpsertProject;
use App\Models\Project;
use App\Models\StatusTransition;
use App\Models\User;
use Laravel\Passport\Passport;

beforeEach(function () {
    $this->user = User::factory()->create();
    Passport::actingAs($this->user, ['mcp:use']);

    $this->makeProject = fn (string $status): Project => Project::create([
        'workspace_id' => $this->user->active_workspace_id,
        'name' => 'Apollo',
        'rigor_level' => 2,
        'status' => $status,
    ]);
});

it('activates a draft project and records a transition', function () {
    $project = ($this->makeProject)('draft');

    ManagementServer::tool(ActivateProject::class, ['project_id' => $project->id])
        ->assertOk()
        ->assertStructuredContent(function ($json) {
            $json->where('from_status', 'draft')->where('to_status', 'active')->etc();
        });

    expect($project->fresh()->status)->toBe('active');

    $transition = StatusTransition::query()->sole();
    expect($transition->to_status)->toBe('active')
        ->and($transition->transitioned_by_user_id)->toBe($this->user->id)
        ->and($transition->transitionable->is($project))->toBeTrue();
});

it('rejects activating a project that is not draft', function () {
    $project = ($this->makeProject)('active');

    ManagementServer::tool(ActivateProject::class, ['project_id' => $project->id])
        ->assertHasErrors(['Cannot activate a project that is active.']);

    expect(StatusTransition::count())->toBe(0);
});

it('archives an active project', function () {
    $project = ($this->makeProject)('active');

    ManagementServer::tool(ArchiveProject::class, ['project_id' => $project->id])
        ->assertOk()
        ->assertStructuredContent(function ($json) {
            $json->where('to_status', 'archived')->etc();
        });

    expect($project->fresh()->status)->toBe('archived');
});

it('rejects archiving a draft project', function () {
    $project = ($this->makeProject)('draft');

    ManagementServer::tool(ArchiveProject::class, ['project_id' => $project->id])
        ->assertHasErrors(['Cannot archive a project that is draft.']);
});

it('closes an active project', function () {
    $project = ($this->makeProject)('active');

    ManagementServer::tool(CloseProject::class, ['project_id' => $project->id])
        ->assertOk()
        ->assertStructuredContent(function ($json) {
            $json->where('to_status', 'closed')->etc();
        });

    expect($project->fresh()->status)->toBe('closed');
});

it('rejects closing an archived project', function () {
    $project = ($this->makeProject)('archived');

    ManagementServer::tool(CloseProject::class, ['project_id' => $project->id])
        ->assertHasErrors(['Cannot close a project that is archived.']);
});

it('restores archived and closed projects to active', function () {
    foreach (['archived', 'closed'] as $status) {
        $project = ($this->makeProject)($status);

        ManagementServer::tool(RestoreProject::class, ['project_id' => $project->id])
            ->assertOk()
            ->assertStructuredContent(function ($json) {
                $json->where('to_status', 'active')->etc();
            });

        expect($project->fresh()->status)->toBe('active');
    }
});

it('rejects restoring an active project', function () {
    $project = ($this->makeProject)('active');

    ManagementServer::tool(RestoreProject::class, ['project_id' => $project->id])
        ->assertHasErrors(['Cannot restore a project that is active.']);
});

it('rejects status passed to upsert-project with a pointer to the transition tools', function () {
    $project = ($this->makeProject)('active');

    ManagementServer::tool(UpsertProject::class, ['id' => $project->id, 'status' => 'archived'])
        ->assertHasErrors(['Project status is not set here. Use the activate-project, archive-project, close-project, and restore-project tools to move status through validated transitions.']);

    expect($project->fresh()->status)->toBe('active');
});

it('rejects status passed to update-project', function () {
    $project = ($this->makeProject)('active');

    ManagementServer::tool(UpdateProject::class, ['id' => $project->id, 'status' => 'archived'])
        ->assertHasErrors(['Project status is not set here.']);

    expect($project->fresh()->status)->toBe('active');
});
