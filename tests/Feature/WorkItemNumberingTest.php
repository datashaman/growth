<?php

use App\Mcp\Servers\PlanningServer;
use App\Mcp\Tools\Plan\UpsertWorkItems;
use App\Models\Project;
use App\Models\User;
use App\Models\WorkItem;
use Illuminate\Database\QueryException;
use Laravel\Passport\Passport;

beforeEach(function () {
    $this->user = User::factory()->create();
    Passport::actingAs($this->user, ['mcp:use']);
    $this->project = Project::create([
        'workspace_id' => $this->user->active_workspace_id,
        'name' => 'Lunar Lander',
        'rigor_level' => 2,
    ]);
});

function makeWorkItem(Project $project, string $name): WorkItem
{
    return WorkItem::create([
        'project_id' => $project->id,
        'name' => $name,
        'kind' => 'task',
    ]);
}

test('work items get a sequential per-project number', function () {
    $first = makeWorkItem($this->project, 'First');
    $second = makeWorkItem($this->project, 'Second');
    $third = makeWorkItem($this->project, 'Third');

    expect($first->number)->toBe(1)
        ->and($second->number)->toBe(2)
        ->and($third->number)->toBe(3);
});

test('numbering is independent per project', function () {
    $other = Project::create([
        'workspace_id' => $this->user->active_workspace_id,
        'name' => 'Mars Lander',
        'rigor_level' => 2,
    ]);

    makeWorkItem($this->project, 'A');
    $otherFirst = makeWorkItem($other, 'B');
    $projectSecond = makeWorkItem($this->project, 'C');

    expect($otherFirst->number)->toBe(1)
        ->and($projectSecond->number)->toBe(2);
});

test('reference formats the number as WI-NNN', function () {
    $item = makeWorkItem($this->project, 'Padded');

    expect($item->reference())->toBe('WI-001');
});

test('a project cannot have two work items with the same number', function () {
    makeWorkItem($this->project, 'First');

    $duplicate = new WorkItem([
        'project_id' => $this->project->id,
        'name' => 'Collides',
        'kind' => 'task',
    ]);
    $duplicate->number = 1;

    expect(fn () => $duplicate->save())->toThrow(QueryException::class);
});

test('the upsert-work-items tool returns the assigned number and reference', function () {
    PlanningServer::tool(UpsertWorkItems::class, [
        'items' => [
            ['project_id' => $this->project->id, 'kind' => 'task', 'name' => 'Notification matrix', 'sort_order' => 50],
        ],
    ])
        ->assertOk()
        ->assertStructuredContent(function ($json) {
            $json->where('items.0.ok', true)
                ->where('items.0.number', 1)
                ->where('items.0.reference', 'WI-001')
                ->where('items.0.sort_order', 50)
                ->etc();
        });
});

test('upsert-work-items can reorder existing items without changing WI references', function () {
    $first = makeWorkItem($this->project, 'First');
    $second = makeWorkItem($this->project, 'Second');

    PlanningServer::tool(UpsertWorkItems::class, [
        'items' => [
            [
                'id' => $second->id,
                'project_id' => $this->project->id,
                'kind' => $second->kind,
                'name' => $second->name,
                'sort_order' => 0,
            ],
        ],
    ])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('items.0.ok', true)
            ->where('items.0.id', $second->id)
            ->where('items.0.reference', 'WI-002')
            ->where('items.0.sort_order', 0)
            ->etc());

    expect($first->fresh()->reference())->toBe('WI-001')
        ->and($second->fresh()->reference())->toBe('WI-002')
        ->and(WorkItem::query()->where('project_id', $this->project->id)->inWbsOrder()->pluck('id')->all())
        ->toBe([$second->id, $first->id]);
});

test('a work item cannot be moved to another project', function () {
    $other = Project::create([
        'workspace_id' => $this->user->active_workspace_id,
        'name' => 'Mars Lander',
        'rigor_level' => 2,
    ]);
    $item = makeWorkItem($this->project, 'Stays put');

    expect(fn () => $item->update(['project_id' => $other->id]))
        ->toThrow(RuntimeException::class);
});

test('the upsert-work-items tool rejects moving an item to another project', function () {
    $other = Project::create([
        'workspace_id' => $this->user->active_workspace_id,
        'name' => 'Mars Lander',
        'rigor_level' => 2,
    ]);
    $item = makeWorkItem($this->project, 'Stays put');

    PlanningServer::tool(UpsertWorkItems::class, [
        'items' => [
            ['id' => $item->id, 'project_id' => $other->id, 'kind' => 'task', 'name' => 'Stays put'],
        ],
    ])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json->where('items.0.ok', false)->etc());

    $item->refresh();
    expect($item->project_id)->toBe($this->project->id)
        ->and($item->number)->toBe(1);
});
