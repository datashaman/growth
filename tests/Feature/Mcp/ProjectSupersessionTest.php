<?php

use App\Mcp\Servers\ManagementServer;
use App\Mcp\Servers\PlanningServer;
use App\Mcp\Tools\Plan\RecordUnattributedEvent;
use App\Mcp\Tools\Projects\ResolveProjectByRepo;
use App\Mcp\Tools\Projects\SupersedeProject;
use App\Models\Project;
use App\Models\StatusTransition;
use App\Models\UnattributedGithubEvent;
use App\Models\User;
use Laravel\Passport\Passport;

beforeEach(function () {
    $this->user = User::factory()->create();
    Passport::actingAs($this->user, ['mcp:use']);

    $this->makeProject = fn (array $attributes = []): Project => Project::create([
        'workspace_id' => $this->user->active_workspace_id,
        'name' => $attributes['name'] ?? 'Growth',
        'rigor_level' => $attributes['rigor_level'] ?? 2,
        'status' => $attributes['status'] ?? 'active',
        'github_repo' => $attributes['github_repo'] ?? null,
    ]);
});

it('supersedes a project, transfers the repo binding, and records audit history', function () {
    $oldProject = ($this->makeProject)([
        'name' => 'Legacy Growth',
        'github_repo' => 'datashaman/growth',
    ]);
    $newProject = ($this->makeProject)(['name' => 'Growth']);

    ManagementServer::tool(SupersedeProject::class, [
        'old_project_id' => $oldProject->id,
        'new_project_id' => $newProject->id,
        'reason' => 'Replanned with a clean structure.',
    ])
        ->assertOk()
        ->assertStructuredContent(function ($json) use ($oldProject, $newProject) {
            $json->where('old_project_id', $oldProject->id)
                ->where('new_project_id', $newProject->id)
                ->where('from_status', 'active')
                ->where('to_status', 'superseded')
                ->where('transferred_github_repo', 'datashaman/growth')
                ->etc();
        });

    $oldProject->refresh();
    $newProject->refresh();

    expect($oldProject->status)->toBe('superseded')
        ->and($oldProject->github_repo)->toBeNull()
        ->and($oldProject->supersededByProject->is($newProject))->toBeTrue()
        ->and($oldProject->supersededByUser->is($this->user))->toBeTrue()
        ->and($oldProject->supersession_reason)->toBe('Replanned with a clean structure.')
        ->and($oldProject->superseded_at)->not->toBeNull()
        ->and($newProject->github_repo)->toBe('datashaman/growth');

    $transition = StatusTransition::query()->sole();
    expect($transition->transitionable->is($oldProject))->toBeTrue()
        ->and($transition->from_status)->toBe('active')
        ->and($transition->to_status)->toBe('superseded')
        ->and($transition->reason)->toBe('Replanned with a clean structure.')
        ->and($transition->transitioned_by_user_id)->toBe($this->user->id);
});

it('routes future repo resolution and unattributed evidence to the replacement project binding', function () {
    $oldProject = ($this->makeProject)(['github_repo' => 'datashaman/growth']);
    $newProject = ($this->makeProject)(['name' => 'Growth v2']);

    ManagementServer::tool(SupersedeProject::class, [
        'old_project_id' => $oldProject->id,
        'new_project_id' => $newProject->id,
    ])->assertOk();

    ManagementServer::tool(ResolveProjectByRepo::class, ['github_repo' => 'datashaman/growth'])
        ->assertOk()
        ->assertStructuredContent(function ($json) use ($newProject) {
            $json->where('found', true)
                ->where('project_id', $newProject->id)
                ->where('status', 'active')
                ->etc();
        });

    PlanningServer::tool(RecordUnattributedEvent::class, [
        'github_repo' => 'datashaman/growth',
        'event_type' => 'check_run',
        'commit_sha' => 'abc123',
        'reason' => 'missing_link',
    ])->assertOk();

    expect(UnattributedGithubEvent::sole()->github_repo)->toBe('datashaman/growth');
});

it('does not overwrite a different repo binding on the replacement project', function () {
    $oldProject = ($this->makeProject)(['github_repo' => 'datashaman/growth']);
    $newProject = ($this->makeProject)(['github_repo' => 'datashaman/other']);

    ManagementServer::tool(SupersedeProject::class, [
        'old_project_id' => $oldProject->id,
        'new_project_id' => $newProject->id,
    ])->assertHasErrors(['Replacement project already has a different GitHub repository binding.']);

    expect($oldProject->fresh()->status)->toBe('active')
        ->and($oldProject->fresh()->github_repo)->toBe('datashaman/growth')
        ->and($newProject->fresh()->github_repo)->toBe('datashaman/other')
        ->and(StatusTransition::count())->toBe(0);
});

it('rejects replacement projects that are not future attribution targets', function () {
    $oldProject = ($this->makeProject)(['github_repo' => 'datashaman/growth']);
    $newProject = ($this->makeProject)(['status' => 'closed']);

    ManagementServer::tool(SupersedeProject::class, [
        'old_project_id' => $oldProject->id,
        'new_project_id' => $newProject->id,
    ])->assertHasErrors(['Replacement project must be draft or active.']);
});
