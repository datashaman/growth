<?php

use App\Growth\Transitions\ActivateProject;
use App\Growth\Transitions\ApproveChangeRequest;
use App\Growth\Transitions\HoldReview;
use App\Mcp\Servers\PlanningServer;
use App\Mcp\Servers\VerificationServer;
use App\Mcp\Tools\Plan\AssignRoles;
use App\Mcp\Tools\Verification\UpsertAnomaly;
use App\Models\ChangeRequest;
use App\Models\Project;
use App\Models\Review;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkspaceMembership;
use App\Notifications\AnomalyOpened;
use App\Notifications\ChangeRequestDecided;
use App\Notifications\ProjectStatusChanged;
use App\Notifications\ReviewHeld;
use App\Notifications\RoleAssigned;
use Illuminate\Support\Facades\Notification;
use Laravel\Passport\Passport;

beforeEach(function () {
    $this->actor = User::factory()->create();
    $this->actingAs($this->actor);
    $this->workspaceId = $this->actor->active_workspace_id;

    // A second member of the actor's workspace — the expected recipient.
    $this->member = User::factory()->create();
    WorkspaceMembership::create([
        'workspace_id' => $this->workspaceId,
        'user_id' => $this->member->id,
        'role' => WorkspaceMembership::ROLE_MEMBER,
    ]);

    $this->project = Project::create([
        'workspace_id' => $this->workspaceId,
        'name' => 'Apollo',
        'rigor_level' => 2,
        'status' => 'draft',
    ]);
});

test('a project status transition notifies workspace members except the actor', function () {
    Notification::fake();

    (new ActivateProject)->apply($this->project, $this->actor);

    Notification::assertSentTo($this->member, ProjectStatusChanged::class);
    Notification::assertNotSentTo($this->actor, ProjectStatusChanged::class);
});

test('a change-request decision notifies workspace members', function () {
    $changeRequest = ChangeRequest::create([
        'project_id' => $this->project->id,
        'title' => 'Adjust orbit',
        'category' => 'scope',
        'status' => 'under_review',
    ]);

    Notification::fake();

    (new ApproveChangeRequest)->apply($changeRequest, $this->actor, 'Approved at CCB');

    Notification::assertSentTo($this->member, ChangeRequestDecided::class);
});

test('holding a review notifies workspace members', function () {
    $review = Review::create([
        'project_id' => $this->project->id,
        'type' => 'inspection',
        'title' => 'SRS inspection',
        'status' => 'in_progress',
    ]);

    Notification::fake();

    (new HoldReview)->apply($review, $this->actor);

    Notification::assertSentTo($this->member, ReviewHeld::class);
});

test('opening an anomaly notifies workspace members', function () {
    Passport::actingAs($this->actor, ['mcp:use']);
    Notification::fake();

    VerificationServer::tool(UpsertAnomaly::class, [
        'project_id' => $this->project->id,
        'severity' => 'high',
        'summary' => 'Crash on boot',
        'description' => 'The service crashes on a cold boot.',
    ])->assertOk();

    Notification::assertSentTo($this->member, AnomalyOpened::class);
});

test('assigning a user to a role notifies that user', function () {
    $role = Role::create([
        'project_id' => $this->project->id,
        'name' => 'Reviewer',
    ]);

    Passport::actingAs($this->actor, ['mcp:use']);
    Notification::fake();

    PlanningServer::tool(AssignRoles::class, [
        'role_ids' => [$role->id],
        'assignee_type' => 'user',
        'assignee_id' => (string) $this->member->id,
    ])->assertOk();

    Notification::assertSentTo($this->member, RoleAssigned::class);
});

test('a workspace notification never reaches another workspace', function () {
    $outsider = User::factory()->create();

    Notification::fake();

    (new ActivateProject)->apply($this->project, $this->actor);

    Notification::assertNotSentTo($outsider, ProjectStatusChanged::class);
});

test('a workspace notification is stamped with workspace, sender, and acting role', function () {
    (new ActivateProject)->apply($this->project, $this->actor);

    $data = $this->member->notifications()->firstOrFail()->data;

    expect($data['workspace_id'])->toBe($this->workspaceId)
        ->and($data['sender']['id'])->toBe((string) $this->actor->id)
        ->and($data['sender']['name'])->toBe($this->actor->name)
        ->and($data)->toHaveKey('acting_surface');
});

test('a workspace notification fans across the database and broadcast channels', function () {
    expect((new ProjectStatusChanged($this->project))->via($this->member))
        ->toBe(['database', 'broadcast']);
});
