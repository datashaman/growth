<?php

use App\Growth\Transitions\BlockWorkItem;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkItem;
use App\Models\WorkspaceMembership;
use App\Notifications\WorkItemStatusChanged;
use Illuminate\Support\Facades\Notification;

/*
 * #399: a work-item status change notifies the roles marked Informed (RACI
 * "i") on the item — and only those — so Consulted/Informed capture earns a
 * real consumer. Responsible/Accountable are routed through the queue digest
 * instead, not this notification path.
 */

beforeEach(function () {
    $this->actor = User::factory()->create();
    $this->actingAs($this->actor);
    $this->workspaceId = $this->actor->active_workspace_id;

    $this->project = Project::create([
        'workspace_id' => $this->workspaceId,
        'name' => 'Apollo',
        'rigor_level' => 2,
    ]);

    $this->item = WorkItem::create([
        'project_id' => $this->project->id,
        'kind' => WorkItem::KINDS[0],
        'name' => 'Wire OAuth',
        'status' => 'in_progress',
    ]);
});

/**
 * A workspace member holding a fresh role that carries the given RACI letter
 * on the work item.
 */
function memberInRaciRole(WorkItem $item, string $raci, string $workspaceId): User
{
    $user = User::factory()->create();
    WorkspaceMembership::create([
        'workspace_id' => $workspaceId,
        'user_id' => $user->id,
        'role' => WorkspaceMembership::ROLE_MEMBER,
    ]);

    $role = Role::create(['project_id' => $item->project_id, 'name' => fake()->unique()->jobTitle()]);
    $role->users()->attach($user);
    $item->raciRoles()->attach($role->id, ['raci' => $raci]);

    return $user;
}

test('blocking a work item notifies its Informed roles', function () {
    $informed = memberInRaciRole($this->item, 'i', $this->workspaceId);

    Notification::fake();

    (new BlockWorkItem)->apply($this->item, $this->actor, 'Upstream API is down');

    Notification::assertSentTo($informed, WorkItemStatusChanged::class);
});

test('the Informed notification skips the actor who caused the change', function () {
    // The actor also holds an Informed role on the item.
    $role = Role::create(['project_id' => $this->project->id, 'name' => 'Lead']);
    $role->users()->attach($this->actor);
    $this->item->raciRoles()->attach($role->id, ['raci' => 'i']);

    Notification::fake();

    (new BlockWorkItem)->apply($this->item, $this->actor, 'Upstream API is down');

    Notification::assertNotSentTo($this->actor, WorkItemStatusChanged::class);
});

test('a workspace member in no Informed role is not notified', function () {
    $informed = memberInRaciRole($this->item, 'i', $this->workspaceId);

    // A plain workspace member with no role on the item at all.
    $bystander = User::factory()->create();
    WorkspaceMembership::create([
        'workspace_id' => $this->workspaceId,
        'user_id' => $bystander->id,
        'role' => WorkspaceMembership::ROLE_MEMBER,
    ]);

    Notification::fake();

    (new BlockWorkItem)->apply($this->item, $this->actor, 'Upstream API is down');

    Notification::assertSentTo($informed, WorkItemStatusChanged::class);
    Notification::assertNotSentTo($bystander, WorkItemStatusChanged::class);
});

test('Responsible and Accountable roles are not notified on the Informed path', function () {
    $responsible = memberInRaciRole($this->item, 'r', $this->workspaceId);
    $accountable = memberInRaciRole($this->item, 'a', $this->workspaceId);

    Notification::fake();

    (new BlockWorkItem)->apply($this->item, $this->actor, 'Upstream API is down');

    Notification::assertNotSentTo($responsible, WorkItemStatusChanged::class);
    Notification::assertNotSentTo($accountable, WorkItemStatusChanged::class);
});

test('a work item with no Informed role sends no notification', function () {
    memberInRaciRole($this->item, 'r', $this->workspaceId);

    Notification::fake();

    (new BlockWorkItem)->apply($this->item, $this->actor, 'Upstream API is down');

    Notification::assertNothingSent();
});
