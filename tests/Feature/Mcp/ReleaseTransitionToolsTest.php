<?php

use App\Mcp\Servers\PlanningServer;
use App\Mcp\Tools\Plan\CancelRelease;
use App\Mcp\Tools\Plan\MarkReleaseReleased;
use App\Mcp\Tools\Plan\PromoteRelease;
use App\Mcp\Tools\Plan\UpsertRelease;
use App\Models\Project;
use App\Models\Release;
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

    $this->makeRelease = fn (string $status): Release => Release::create([
        'project_id' => $this->project->id,
        'version' => '1.0.0',
        'status' => $status,
    ]);
});

it('promotes a planned release and records a transition', function () {
    $release = ($this->makeRelease)('planned');

    PlanningServer::tool(PromoteRelease::class, ['release_id' => $release->id])
        ->assertOk()
        ->assertStructuredContent(function ($json) use ($release) {
            $json->where('release_id', $release->id)
                ->where('from_status', 'planned')
                ->where('to_status', 'candidate')
                ->etc();
        });

    expect($release->fresh()->status)->toBe('candidate');

    $transition = StatusTransition::query()->sole();
    expect($transition->to_status)->toBe('candidate')
        ->and($transition->transitioned_by_user_id)->toBe($this->user->id)
        ->and($transition->transitionable->is($release))->toBeTrue();
});

it('rejects promoting a release that is not planned', function () {
    $release = ($this->makeRelease)('released');

    PlanningServer::tool(PromoteRelease::class, ['release_id' => $release->id])
        ->assertHasErrors(['Cannot promote a release that is released.']);

    expect($release->fresh()->status)->toBe('released');
    expect(StatusTransition::count())->toBe(0);
});

it('marks a candidate release as released', function () {
    $release = ($this->makeRelease)('candidate');

    PlanningServer::tool(MarkReleaseReleased::class, ['release_id' => $release->id, 'reason' => 'Shipped'])
        ->assertOk()
        ->assertStructuredContent(function ($json) {
            $json->where('from_status', 'candidate')
                ->where('to_status', 'released')
                ->etc();
        });

    expect($release->fresh()->status)->toBe('released');
    expect(StatusTransition::query()->sole()->reason)->toBe('Shipped');
});

it('rejects marking a release released when it is not a candidate', function () {
    $release = ($this->makeRelease)('planned');

    PlanningServer::tool(MarkReleaseReleased::class, ['release_id' => $release->id])
        ->assertHasErrors(['Cannot release a release that is planned.']);

    expect($release->fresh()->status)->toBe('planned');
});

it('cancels a planned release', function () {
    $release = ($this->makeRelease)('planned');

    PlanningServer::tool(CancelRelease::class, ['release_id' => $release->id])
        ->assertOk();

    expect($release->fresh()->status)->toBe('cancelled');
});

it('cancels a candidate release', function () {
    $release = ($this->makeRelease)('candidate');

    PlanningServer::tool(CancelRelease::class, ['release_id' => $release->id])
        ->assertOk();

    expect($release->fresh()->status)->toBe('cancelled');
});

it('rejects cancelling a released release', function () {
    $release = ($this->makeRelease)('released');

    PlanningServer::tool(CancelRelease::class, ['release_id' => $release->id])
        ->assertHasErrors(['Cannot cancel a release that is released.']);

    expect($release->fresh()->status)->toBe('released');
});

it('still accepts a raw status on upsert-release so the sync path is unchanged', function () {
    PlanningServer::tool(UpsertRelease::class, [
        'project_id' => $this->project->id,
        'version' => '2.0.0',
        'status' => 'released',
    ])
        ->assertOk()
        ->assertStructuredContent(function ($json) {
            $json->where('status', 'released')->etc();
        });

    expect(Release::where('version', '2.0.0')->sole()->status)->toBe('released');
});

it('rejects a transition on a release the user does not own', function () {
    $stranger = User::factory()->create();
    $strangerProject = Project::create([
        'workspace_id' => $stranger->active_workspace_id,
        'name' => 'Foreign',
        'rigor_level' => 1,
    ]);
    $foreignRelease = Release::create([
        'project_id' => $strangerProject->id,
        'version' => '9.9.9',
        'status' => 'planned',
    ]);

    PlanningServer::tool(PromoteRelease::class, ['release_id' => $foreignRelease->id])
        ->assertHasErrors();

    expect($foreignRelease->fresh()->status)->toBe('planned');
});
