<?php

use App\Mcp\Servers\PlanningServer;
use App\Mcp\Tools\Plan\BlockWorkItem;
use App\Mcp\Tools\Plan\CancelWorkItem;
use App\Mcp\Tools\Plan\ReopenWorkItem;
use App\Mcp\Tools\Plan\UnblockWorkItem;
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
        'name' => 'Lifecycle',
        'rigor_level' => 2,
    ]);

    $this->makeItem = fn (string $status): WorkItem => WorkItem::create([
        'project_id' => $this->project->id,
        'kind' => 'task',
        'name' => 'Item',
        'status' => $status,
    ]);
});

it('blocks a todo work item and records the blocker reason', function () {
    $item = ($this->makeItem)('todo');

    PlanningServer::tool(BlockWorkItem::class, ['work_item_id' => $item->id, 'reason' => 'Waiting on vendor'])
        ->assertOk()
        ->assertStructuredContent(function ($json) {
            $json->where('from_status', 'todo')->where('to_status', 'blocked')->etc();
        });

    expect($item->fresh()->status)->toBe('blocked');
    expect(StatusTransition::query()->sole()->reason)->toBe('Waiting on vendor');
});

it('blocks an in_progress work item', function () {
    $item = ($this->makeItem)('in_progress');

    PlanningServer::tool(BlockWorkItem::class, ['work_item_id' => $item->id, 'reason' => 'Blocked'])
        ->assertOk();

    expect($item->fresh()->status)->toBe('blocked');
});

it('requires a reason to block a work item', function () {
    $item = ($this->makeItem)('todo');

    PlanningServer::tool(BlockWorkItem::class, ['work_item_id' => $item->id])
        ->assertHasErrors();

    expect($item->fresh()->status)->toBe('todo');
});

it('rejects blocking a done work item', function () {
    $item = ($this->makeItem)('done');

    PlanningServer::tool(BlockWorkItem::class, ['work_item_id' => $item->id, 'reason' => 'too late'])
        ->assertHasErrors(['Cannot block a work item that is done.']);

    expect(StatusTransition::count())->toBe(0);
});

it('unblocks a blocked work item back to in_progress', function () {
    $item = ($this->makeItem)('blocked');

    PlanningServer::tool(UnblockWorkItem::class, ['work_item_id' => $item->id])
        ->assertOk()
        ->assertStructuredContent(function ($json) {
            $json->where('from_status', 'blocked')->where('to_status', 'in_progress')->etc();
        });

    expect($item->fresh()->status)->toBe('in_progress');
});

it('rejects unblocking a work item that is not blocked', function () {
    $item = ($this->makeItem)('todo');

    PlanningServer::tool(UnblockWorkItem::class, ['work_item_id' => $item->id])
        ->assertHasErrors(['Cannot unblock a work item that is todo.']);
});

it('cancels work items from todo, in_progress, and blocked', function () {
    foreach (['todo', 'in_progress', 'blocked'] as $status) {
        $item = ($this->makeItem)($status);

        PlanningServer::tool(CancelWorkItem::class, ['work_item_id' => $item->id])->assertOk();

        expect($item->fresh()->status)->toBe('cancelled');
    }
});

it('rejects cancelling a done work item', function () {
    $item = ($this->makeItem)('done');

    PlanningServer::tool(CancelWorkItem::class, ['work_item_id' => $item->id])
        ->assertHasErrors(['Cannot cancel a work item that is done.']);
});

it('reopens done and cancelled work items back to todo', function () {
    foreach (['done', 'cancelled'] as $status) {
        $item = ($this->makeItem)($status);

        PlanningServer::tool(ReopenWorkItem::class, ['work_item_id' => $item->id])
            ->assertOk()
            ->assertStructuredContent(function ($json) {
                $json->where('to_status', 'todo')->etc();
            });

        expect($item->fresh()->status)->toBe('todo');
    }
});

it('rejects reopening an in_progress work item', function () {
    $item = ($this->makeItem)('in_progress');

    PlanningServer::tool(ReopenWorkItem::class, ['work_item_id' => $item->id])
        ->assertHasErrors(['Cannot reopen a work item that is in progress.']);
});
