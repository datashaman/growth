<?php

use App\Mcp\Servers\PlanningServer;
use App\Mcp\Tools\Plan\ResolveWorkItemByReference;
use App\Models\Project;
use App\Models\User;
use App\Models\WorkItem;
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

it('resolves a WI-NNN reference to its work item', function () {
    PlanningServer::tool(ResolveWorkItemByReference::class, [
        'github_repo' => 'datashaman/growth',
        'reference' => 'WI-'.$this->workItem->number,
    ])
        ->assertOk()
        ->assertStructuredContent(function ($json) {
            $json->where('found', true)
                ->where('github_repo', 'datashaman/growth')
                ->where('reference', 'WI-'.$this->workItem->number)
                ->where('work_item_id', $this->workItem->id)
                ->where('work_item_name', 'Ship the lander')
                ->where('work_item_status', 'in_progress');
        });
});

it('accepts a bare number, lowercase prefix, and leading zeros', function (string $reference) {
    PlanningServer::tool(ResolveWorkItemByReference::class, [
        'github_repo' => 'datashaman/growth',
        'reference' => $reference,
    ])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('found', true)
            ->where('work_item_id', $this->workItem->id)
            ->etc());
})->with([
    // The lone work item in the project is numbered 1; each of these is a
    // spelling of "1". A dataset closure cannot reach $this, so the number
    // is hard-coded rather than read from $this->workItem.
    'bare number' => ['1'],
    'lowercase prefix' => ['wi-1'],
    'leading zeros' => ['WI-001'],
]);

it('reports not found when no work item carries the number without erroring', function () {
    PlanningServer::tool(ResolveWorkItemByReference::class, [
        'github_repo' => 'datashaman/growth',
        'reference' => 'WI-999',
    ])
        ->assertOk()
        ->assertStructuredContent(function ($json) {
            $json->where('found', false)
                ->where('github_repo', 'datashaman/growth')
                ->where('reference', 'WI-999')
                ->where('work_item_id', null)
                ->where('work_item_name', null)
                ->where('work_item_status', null);
        });
});

it('reports not found for an unparseable reference', function () {
    PlanningServer::tool(ResolveWorkItemByReference::class, [
        'github_repo' => 'datashaman/growth',
        'reference' => 'not-a-reference',
    ])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json->where('found', false)->etc());
});

it('does not resolve a number from another repo', function () {
    $other = Project::create([
        'workspace_id' => $this->user->active_workspace_id,
        'name' => 'Other',
        'rigor_level' => 2,
        'github_repo' => 'datashaman/other',
    ]);
    WorkItem::create([
        'project_id' => $other->id,
        'name' => 'Elsewhere',
        'kind' => 'task',
        'status' => 'in_progress',
    ]);

    PlanningServer::tool(ResolveWorkItemByReference::class, [
        'github_repo' => 'datashaman/other',
        'reference' => 'WI-'.$this->workItem->number,
    ])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('found', true)
            ->where('work_item_name', 'Elsewhere')
            ->etc());

    PlanningServer::tool(ResolveWorkItemByReference::class, [
        'github_repo' => 'datashaman/growth',
        'reference' => 'WI-'.$this->workItem->number,
    ])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('found', true)
            ->where('work_item_name', 'Ship the lander')
            ->etc());
});

it('does not resolve a number in another workspace', function () {
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

    PlanningServer::tool(ResolveWorkItemByReference::class, [
        'github_repo' => 'datashaman/foreign',
        'reference' => 'WI-'.$foreignItem->number,
    ])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json->where('found', false)->etc());
});

it('rejects a malformed repo argument', function () {
    PlanningServer::tool(ResolveWorkItemByReference::class, [
        'github_repo' => 'not-a-repo',
        'reference' => 'WI-1',
    ])->assertHasErrors();
});
