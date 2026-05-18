<?php

use App\Growth\Digest\WhatNeedsMeDigest;
use App\Mcp\Servers\ReadonlyServer;
use App\Mcp\Tools\Dashboard\SummarizeMyQueue;
use App\Models\ChangeRequest;
use App\Models\DecisionRequest;
use App\Models\Project;
use App\Models\Requirement;
use App\Models\Review;
use App\Models\ReviewParticipant;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkItem;
use Laravel\Passport\Passport;

beforeEach(function () {
    $this->actor = User::factory()->create();
    Passport::actingAs($this->actor, ['mcp:use']);

    $this->project = Project::create([
        'workspace_id' => $this->actor->active_workspace_id,
        'name' => 'Festival Market',
        'rigor_level' => 2,
    ]);

    $this->role = Role::create(['project_id' => $this->project->id, 'name' => 'Product Owner']);
    $this->actor->roles()->attach($this->role);

    $this->otherRole = Role::create(['project_id' => $this->project->id, 'name' => 'Architect']);

    $this->approvedChange = fn (string $roleId): ChangeRequest => ChangeRequest::create([
        'project_id' => $this->project->id,
        'title' => 'Add a second stage',
        'category' => 'scope',
        'priority' => 'medium',
        'status' => 'approved',
        'decision' => 'approved',
        'requester_role_id' => $roleId,
    ]);

    $this->reviewAwaiting = function (string $roleId): Review {
        $review = Review::create([
            'project_id' => $this->project->id,
            'type' => 'inspection',
            'title' => 'SRS inspection',
            'status' => 'planned',
        ]);

        ReviewParticipant::create([
            'review_id' => $review->id,
            'role_id' => $roleId,
            'responsibility' => 'reviewer',
            'attendance_status' => 'invited',
        ]);

        return $review;
    };

    $this->blockedItem = fn (string $roleId): WorkItem => WorkItem::create([
        'project_id' => $this->project->id,
        'kind' => 'task',
        'name' => 'Wire the booth',
        'status' => 'blocked',
        'responsible_role_id' => $roleId,
    ]);
});

it('groups every routed kind into its own bucket', function () {
    ($this->approvedChange)($this->role->id);
    ($this->reviewAwaiting)($this->role->id);
    ($this->blockedItem)($this->role->id);
    DecisionRequest::factory()->create([
        'project_id' => $this->project->id,
        'target_role_id' => $this->role->id,
        'status' => 'open',
    ]);

    ReadonlyServer::tool(SummarizeMyQueue::class, ['project_id' => $this->project->id])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->has('change_requests', 1)
            ->has('reviews', 1)
            ->has('blocked_work_items', 1)
            ->has('decision_requests', 1)
            ->where('change_requests.0.reference', 'CR-001')
            ->where('blocked_work_items.0.reference', 'WI-001')
            ->etc());
});

it('omits change requests requested by a role the caller does not hold', function () {
    ($this->approvedChange)($this->otherRole->id);

    ReadonlyServer::tool(SummarizeMyQueue::class, ['project_id' => $this->project->id])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json->has('change_requests', 0)->etc());
});

it('omits reviews awaiting a role the caller does not hold', function () {
    ($this->reviewAwaiting)($this->otherRole->id);

    ReadonlyServer::tool(SummarizeMyQueue::class, ['project_id' => $this->project->id])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json->has('reviews', 0)->etc());
});

it('omits a review the caller has already signed off', function () {
    $review = ($this->reviewAwaiting)($this->role->id);
    $review->participants()->update(['signed_off_at' => now()]);

    ReadonlyServer::tool(SummarizeMyQueue::class, ['project_id' => $this->project->id])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json->has('reviews', 0)->etc());
});

it('omits blocked work items routed to a role the caller does not hold', function () {
    ($this->blockedItem)($this->otherRole->id);

    ReadonlyServer::tool(SummarizeMyQueue::class, ['project_id' => $this->project->id])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json->has('blocked_work_items', 0)->etc());
});

it('includes a blocked work item via a RACI responsible assignment', function () {
    $item = ($this->blockedItem)($this->otherRole->id);
    $item->raciRoles()->attach($this->role->id, ['raci' => 'r']);

    ReadonlyServer::tool(SummarizeMyQueue::class, ['project_id' => $this->project->id])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json->has('blocked_work_items', 1)->etc());
});

it('omits decision requests routed to a role the caller does not hold', function () {
    DecisionRequest::factory()->create([
        'project_id' => $this->project->id,
        'target_role_id' => $this->otherRole->id,
        'status' => 'open',
    ]);

    ReadonlyServer::tool(SummarizeMyQueue::class, ['project_id' => $this->project->id])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json->has('decision_requests', 0)->etc());
});

it('does not summarize a project in another workspace', function () {
    $other = User::factory()->create();
    $otherProject = Project::create([
        'workspace_id' => $other->active_workspace_id,
        'name' => 'Elsewhere',
        'rigor_level' => 2,
    ]);

    ReadonlyServer::tool(SummarizeMyQueue::class, ['project_id' => $otherProject->id])
        ->assertHasErrors();
});

it('attributes lint errors to the owning role and lists role-less ones as unowned', function () {
    // An approved change request with no impacted artifacts yields the
    // change.impacts.empty error, attributable to its requester role.
    ($this->approvedChange)($this->role->id);

    // A requirement containing TBD yields the incomplete error — requirements
    // carry no role column, so it can only land in the unowned bucket.
    Requirement::create([
        'project_id' => $this->project->id,
        'doc' => 'srs',
        'type' => 'functional',
        'text' => 'The system shall handle TBD payment methods',
    ]);

    $digest = app(WhatNeedsMeDigest::class)->for($this->project, $this->actor);

    expect(collect($digest['lint_findings'])->pluck('rule'))->toContain('change.impacts.empty')
        ->and(collect($digest['unowned_lint_findings'])->pluck('rule'))->toContain('incomplete')
        ->and(collect($digest['lint_findings'])->every(fn ($f) => $f['severity'] === 'error'))->toBeTrue();
});

it('drops lint errors attributed to a role the caller does not hold', function () {
    ($this->approvedChange)($this->otherRole->id);

    $digest = app(WhatNeedsMeDigest::class)->for($this->project, $this->actor);

    expect(collect($digest['lint_findings'])->pluck('rule'))->not->toContain('change.impacts.empty');
});
