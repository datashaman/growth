<?php

use App\Mcp\Servers\AllServer;
use App\Mcp\Servers\IntakeServer;
use App\Mcp\Tools\Projects\CreateProject;
use App\Mcp\Tools\Projects\UpdateProject;
use App\Mcp\Tools\UpsertProject;
use App\Models\Project;
use App\Models\User;
use Laravel\Passport\Passport;

beforeEach(function () {
    $this->user = User::factory()->create();
    Passport::actingAs($this->user, ['mcp:use']);
});

it('defaults new projects to active status', function () {
    IntakeServer::tool(UpsertProject::class, ['name' => 'New', 'rigor_level' => 1])
        ->assertOk()
        ->assertStructuredContent(function ($json) {
            $json->where('status', 'active')->etc();
        });

    expect(Project::where('name', 'New')->sole()->status)->toBe('active');
});

it('refuses content edits on an archived project but allows status to change', function () {
    $project = Project::create(['user_id' => $this->user->id, 'name' => 'P', 'rigor_level' => 1, 'status' => 'archived']);

    IntakeServer::tool(UpsertProject::class, [
        'id' => $project->id,
        'name' => 'Renamed',
    ])->assertHasErrors(['archived and cannot be edited']);

    expect(Project::find($project->id)->name)->toBe('P');

    IntakeServer::tool(UpsertProject::class, [
        'id' => $project->id,
        'status' => 'active',
    ])->assertOk()
        ->assertStructuredContent(function ($json) {
            $json->where('status', 'active')->etc();
        });

    IntakeServer::tool(UpsertProject::class, [
        'id' => $project->id,
        'name' => 'Renamed',
    ])->assertOk();

    expect(Project::find($project->id)->name)->toBe('Renamed');
});

it('blocks update-project content edits on closed projects', function () {
    $project = Project::create(['user_id' => $this->user->id, 'name' => 'P', 'rigor_level' => 1, 'status' => 'closed']);

    AllServer::tool(UpdateProject::class, [
        'id' => $project->id,
        'description' => 'edit me',
    ])->assertHasErrors(['closed and cannot be edited']);

    expect(Project::find($project->id)->description)->toBeNull();
});

it('allows draft status for new projects through create-project', function () {
    AllServer::tool(CreateProject::class, [
        'name' => 'Drafty',
        'status' => 'draft',
    ])->assertOk()
        ->assertStructuredContent(function ($json) {
            $json->where('status', 'draft')->etc();
        });
});

it('rejects unknown status values', function () {
    AllServer::tool(CreateProject::class, [
        'name' => 'Bad',
        'status' => 'nope',
    ])->assertHasErrors();
});
