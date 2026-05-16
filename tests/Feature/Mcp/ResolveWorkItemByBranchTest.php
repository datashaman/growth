<?php

use App\Mcp\Servers\PlanningServer;
use App\Mcp\Tools\Plan\ResolveWorkItemByBranch;
use App\Models\Project;
use App\Models\User;
use App\Models\WorkItem;
use App\Models\WorkItemDeliveryLink;
use Laravel\Passport\Passport;

beforeEach(function () {
    $this->user = User::factory()->create();
    Passport::actingAs($this->user, ['mcp:use']);

    $this->project = Project::create([
        'workspace_id' => $this->user->active_workspace_id,
        'name' => 'Growth',
        'rigor_level' => 2,
        'github_repo' => 'datashaman/growth',
    ]);

    $this->workItem = WorkItem::create([
        'project_id' => $this->project->id,
        'name' => 'Ship the lander',
        'kind' => 'task',
        'status' => 'in_progress',
    ]);
});

it('resolves a branch delivery link to its work item', function () {
    WorkItemDeliveryLink::create([
        'work_item_id' => $this->workItem->id,
        'type' => 'branch',
        'ref' => 'feature/lander',
    ]);

    PlanningServer::tool(ResolveWorkItemByBranch::class, [
        'github_repo' => 'datashaman/growth',
        'branch' => 'feature/lander',
    ])
        ->assertOk()
        ->assertStructuredContent(function ($json) {
            $json->where('found', true)
                ->where('ambiguous', false)
                ->where('github_repo', 'datashaman/growth')
                ->where('branch', 'feature/lander')
                ->where('work_item_id', $this->workItem->id)
                ->where('work_item_name', 'Ship the lander')
                ->where('work_item_status', 'in_progress');
        });
});

it('reports not found when no branch link matches without erroring', function () {
    PlanningServer::tool(ResolveWorkItemByBranch::class, [
        'github_repo' => 'datashaman/growth',
        'branch' => 'feature/never-bound',
    ])
        ->assertOk()
        ->assertStructuredContent(function ($json) {
            $json->where('found', false)
                ->where('ambiguous', false)
                ->where('github_repo', 'datashaman/growth')
                ->where('branch', 'feature/never-bound')
                ->where('work_item_id', null)
                ->where('work_item_name', null)
                ->where('work_item_status', null);
        });
});

it('ignores a pull_request delivery link that shares the ref', function () {
    WorkItemDeliveryLink::create([
        'work_item_id' => $this->workItem->id,
        'type' => 'pull_request',
        'ref' => 'shared-ref',
    ]);

    PlanningServer::tool(ResolveWorkItemByBranch::class, [
        'github_repo' => 'datashaman/growth',
        'branch' => 'shared-ref',
    ])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json->where('found', false)->etc());
});

it('does not resolve a branch bound in another repo', function () {
    $other = Project::create([
        'workspace_id' => $this->user->active_workspace_id,
        'name' => 'Other',
        'rigor_level' => 2,
        'github_repo' => 'datashaman/other',
    ]);
    $otherItem = WorkItem::create([
        'project_id' => $other->id,
        'name' => 'Elsewhere',
        'kind' => 'task',
        'status' => 'in_progress',
    ]);
    WorkItemDeliveryLink::create([
        'work_item_id' => $otherItem->id,
        'type' => 'branch',
        'ref' => 'main',
    ]);

    PlanningServer::tool(ResolveWorkItemByBranch::class, [
        'github_repo' => 'datashaman/growth',
        'branch' => 'main',
    ])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json->where('found', false)->etc());
});

it('reports ambiguity when two work items share a branch link', function () {
    $second = WorkItem::create([
        'project_id' => $this->project->id,
        'name' => 'Also the lander',
        'kind' => 'task',
        'status' => 'in_progress',
    ]);
    foreach ([$this->workItem, $second] as $item) {
        WorkItemDeliveryLink::create([
            'work_item_id' => $item->id,
            'type' => 'branch',
            'ref' => 'feature/contested',
        ]);
    }

    PlanningServer::tool(ResolveWorkItemByBranch::class, [
        'github_repo' => 'datashaman/growth',
        'branch' => 'feature/contested',
    ])
        ->assertOk()
        ->assertStructuredContent(function ($json) {
            $json->where('found', false)
                ->where('ambiguous', true)
                ->where('work_item_id', null)
                ->etc();
        });
});

it('does not resolve a branch bound in another workspace', function () {
    $other = User::factory()->create();
    $foreignProject = Project::create([
        'workspace_id' => $other->active_workspace_id,
        'name' => 'Foreign',
        'rigor_level' => 2,
        'github_repo' => 'datashaman/foreign',
    ]);
    $foreignItem = WorkItem::create([
        'project_id' => $foreignProject->id,
        'name' => 'Foreign work',
        'kind' => 'task',
        'status' => 'in_progress',
    ]);
    WorkItemDeliveryLink::create([
        'work_item_id' => $foreignItem->id,
        'type' => 'branch',
        'ref' => 'feature/lander',
    ]);

    PlanningServer::tool(ResolveWorkItemByBranch::class, [
        'github_repo' => 'datashaman/foreign',
        'branch' => 'feature/lander',
    ])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json->where('found', false)->etc());
});

it('rejects a malformed repo argument', function () {
    PlanningServer::tool(ResolveWorkItemByBranch::class, [
        'github_repo' => 'not-a-repo',
        'branch' => 'main',
    ])->assertHasErrors();
});
