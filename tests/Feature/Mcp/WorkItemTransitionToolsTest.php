<?php

use App\Mcp\Servers\PlanningServer;
use App\Mcp\Tools\Plan\CompleteWorkItem;
use App\Mcp\Tools\Plan\StartWorkItem;
use App\Models\Project;
use App\Models\StatusTransition;
use App\Models\User;
use App\Models\WorkItem;
use Laravel\Passport\Passport;

beforeEach(function () {
    $this->user = User::factory()->create();
    Passport::actingAs($this->user, ['mcp:use']);

    $this->project = Project::create([
        'workspace_id' => $this->user->active_workspace_id,
        'name' => 'Transitions',
        'rigor_level' => 2,
    ]);

    $this->makeItem = fn (string $status): WorkItem => WorkItem::create([
        'project_id' => $this->project->id,
        'kind' => 'task',
        'name' => 'Item',
        'status' => $status,
    ]);
});

it('starts a todo work item and records a transition', function () {
    $item = ($this->makeItem)('todo');

    PlanningServer::tool(StartWorkItem::class, ['work_item_id' => $item->id])
        ->assertOk()
        ->assertStructuredContent(function ($json) use ($item) {
            $json->where('work_item_id', $item->id)
                ->where('from_status', 'todo')
                ->where('to_status', 'in_progress')
                ->etc();
        });

    expect($item->fresh()->status)->toBe('in_progress');

    $transition = StatusTransition::query()->sole();
    expect($transition->from_status)->toBe('todo')
        ->and($transition->to_status)->toBe('in_progress')
        ->and($transition->transitioned_by_user_id)->toBe($this->user->id)
        ->and($transition->transitioned_at)->not->toBeNull()
        ->and($transition->transitionable->is($item))->toBeTrue();
});

it('rejects starting a work item that is not todo', function () {
    $item = ($this->makeItem)('done');

    PlanningServer::tool(StartWorkItem::class, ['work_item_id' => $item->id])
        ->assertHasErrors(['Cannot start a work item that is done.']);

    expect($item->fresh()->status)->toBe('done');
    expect(StatusTransition::count())->toBe(0);
});

it('completes an in_progress work item and records a transition', function () {
    $item = ($this->makeItem)('in_progress');

    PlanningServer::tool(CompleteWorkItem::class, ['work_item_id' => $item->id, 'reason' => 'Shipped'])
        ->assertOk()
        ->assertStructuredContent(function ($json) {
            $json->where('from_status', 'in_progress')
                ->where('to_status', 'done')
                ->etc();
        });

    expect($item->fresh()->status)->toBe('done');

    $transition = StatusTransition::query()->sole();
    expect($transition->to_status)->toBe('done')
        ->and($transition->reason)->toBe('Shipped');
});

it('rejects completing a work item that is not in_progress', function () {
    $item = ($this->makeItem)('todo');

    PlanningServer::tool(CompleteWorkItem::class, ['work_item_id' => $item->id])
        ->assertHasErrors(['Cannot complete a work item that is todo.']);

    expect($item->fresh()->status)->toBe('todo');
    expect(StatusTransition::count())->toBe(0);
});

it('rejects a transition on a work item the user does not own', function () {
    $stranger = User::factory()->create();
    $strangerProject = Project::create([
        'workspace_id' => $stranger->active_workspace_id,
        'name' => 'Foreign',
        'rigor_level' => 1,
    ]);
    $foreignItem = WorkItem::create([
        'project_id' => $strangerProject->id,
        'kind' => 'task',
        'name' => 'Off limits',
        'status' => 'todo',
    ]);

    PlanningServer::tool(StartWorkItem::class, ['work_item_id' => $foreignItem->id])
        ->assertHasErrors();

    expect($foreignItem->fresh()->status)->toBe('todo');
});
