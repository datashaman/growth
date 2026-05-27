<?php

use App\Growth\Transitions\BlockWorkItem;
use App\Growth\Transitions\CancelWorkItem;
use App\Growth\Transitions\CompleteWorkItem;
use App\Growth\Transitions\IllegalTransitionException;
use App\Growth\Transitions\ReopenWorkItem;
use App\Growth\Transitions\ResetWorkItem;
use App\Growth\Transitions\StartWorkItem;
use App\Growth\Transitions\UnblockWorkItem;
use App\Models\Project;
use App\Models\StatusTransition;
use App\Models\User;
use App\Models\WorkItem;

beforeEach(function () {
    $this->user = User::factory()->create();
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

// ---- base transition action ----

it('applies a legal transition and records an audit row', function () {
    $item = ($this->makeItem)('todo');

    $transition = (new StartWorkItem)->apply($item, $this->user, 'Kicking off');

    expect($item->fresh()->status)->toBe('in_progress')
        ->and($transition->from_status)->toBe('todo')
        ->and($transition->to_status)->toBe('in_progress')
        ->and($transition->reason)->toBe('Kicking off')
        ->and($transition->transitioned_by_user_id)->toBe($this->user->id)
        ->and(StatusTransition::count())->toBe(1);
});

it('rejects an illegal source state without writing an audit row', function () {
    $item = ($this->makeItem)('blocked');

    expect(fn () => (new CompleteWorkItem)->apply($item))
        ->toThrow(IllegalTransitionException::class, 'Cannot complete a work item that is blocked.');

    expect($item->fresh()->status)->toBe('blocked')
        ->and(StatusTransition::count())->toBe(0);
});

it('records a null actor when no user is supplied', function () {
    $item = ($this->makeItem)('in_progress');

    $transition = (new CompleteWorkItem)->apply($item);

    expect($transition->transitioned_by_user_id)->toBeNull()
        ->and($item->fresh()->status)->toBe('done');
});

it('resets an in_progress work item back to todo and records an audit row', function () {
    $item = ($this->makeItem)('in_progress');

    $transition = (new ResetWorkItem)->apply($item, $this->user, 'Started by mistake');

    expect($item->fresh()->status)->toBe('todo')
        ->and($transition->from_status)->toBe('in_progress')
        ->and($transition->to_status)->toBe('todo')
        ->and($transition->reason)->toBe('Started by mistake')
        ->and($transition->transitioned_by_user_id)->toBe($this->user->id);
});

it('rejects resetting a work item that is not in_progress', function () {
    $item = ($this->makeItem)('blocked');

    expect(fn () => (new ResetWorkItem)->apply($item))
        ->toThrow(IllegalTransitionException::class, 'Cannot reset a work item that is blocked.');

    expect($item->fresh()->status)->toBe('blocked')
        ->and(StatusTransition::count())->toBe(0);
});

it('rolls a parent work package to done when every direct child is done', function () {
    $parent = WorkItem::create([
        'project_id' => $this->project->id,
        'kind' => 'work_package',
        'name' => 'Parent package',
        'status' => 'todo',
    ]);
    WorkItem::create([
        'project_id' => $this->project->id,
        'parent_id' => $parent->id,
        'kind' => 'deliverable',
        'name' => 'Already done',
        'status' => 'done',
    ]);
    $lastChild = WorkItem::create([
        'project_id' => $this->project->id,
        'parent_id' => $parent->id,
        'kind' => 'deliverable',
        'name' => 'Last child',
        'status' => 'in_progress',
    ]);

    (new CompleteWorkItem)->apply($lastChild, $this->user, 'Last child shipped');

    expect($parent->fresh()->status)->toBe('done');

    $parentTransition = StatusTransition::query()
        ->where('transitionable_id', $parent->id)
        ->sole();

    expect($parentTransition->from_status)->toBe('todo')
        ->and($parentTransition->to_status)->toBe('done')
        ->and($parentTransition->transitioned_by_user_id)->toBe($this->user->id)
        ->and($parentTransition->reason)->toBe('All child work items are done.');
});

it('does not roll a parent work package to done while any direct child remains open', function () {
    $parent = WorkItem::create([
        'project_id' => $this->project->id,
        'kind' => 'work_package',
        'name' => 'Parent package',
        'status' => 'todo',
    ]);
    $lastChild = WorkItem::create([
        'project_id' => $this->project->id,
        'parent_id' => $parent->id,
        'kind' => 'deliverable',
        'name' => 'Last child',
        'status' => 'in_progress',
    ]);
    WorkItem::create([
        'project_id' => $this->project->id,
        'parent_id' => $parent->id,
        'kind' => 'deliverable',
        'name' => 'Still open',
        'status' => 'todo',
    ]);

    (new CompleteWorkItem)->apply($lastChild, $this->user, 'One child shipped');

    expect($parent->fresh()->status)->toBe('in_progress');
    expect(StatusTransition::query()
        ->where('transitionable_id', $parent->id)
        ->where('to_status', 'in_progress')
        ->exists())->toBeTrue();
});

it('rolls parent status from direct child states', function (array $children, string $trigger, string $initialParentStatus, string $expected, string $reason) {
    $parent = WorkItem::create([
        'project_id' => $this->project->id,
        'kind' => 'work_package',
        'name' => 'Parent package',
        'status' => $initialParentStatus,
    ]);
    $triggerChild = null;

    foreach ($children as $index => $status) {
        $child = WorkItem::create([
            'project_id' => $this->project->id,
            'parent_id' => $parent->id,
            'kind' => 'deliverable',
            'name' => 'Child '.$index,
            'status' => $status,
        ]);

        if ($index === 0) {
            $triggerChild = $child;
        }
    }

    match ($trigger) {
        'start' => (new StartWorkItem)->apply($triggerChild, $this->user),
        'reset' => (new ResetWorkItem)->apply($triggerChild, $this->user),
        'block' => (new BlockWorkItem)->apply($triggerChild, $this->user, 'Blocked downstream'),
        'unblock' => (new UnblockWorkItem)->apply($triggerChild, $this->user),
        'complete' => (new CompleteWorkItem)->apply($triggerChild, $this->user),
        'reopen' => (new ReopenWorkItem)->apply($triggerChild, $this->user),
        'cancel' => (new CancelWorkItem)->apply($triggerChild, $this->user),
    };

    expect($parent->fresh()->status)->toBe($expected);

    $parentTransition = StatusTransition::query()
        ->where('transitionable_id', $parent->id)
        ->sole();

    expect($parentTransition->to_status)->toBe($expected)
        ->and($parentTransition->reason)->toBe($reason);
})->with([
    'all done' => [['in_progress', 'done'], 'complete', 'todo', 'done', 'All child work items are done.'],
    'all cancelled' => [['blocked', 'cancelled'], 'cancel', 'done', 'cancelled', 'Child work item statuses rolled up to cancelled.'],
    'all todo' => [['in_progress', 'todo'], 'reset', 'done', 'todo', 'Child work item statuses rolled up to todo.'],
    'any blocked' => [['in_progress', 'done'], 'block', 'done', 'blocked', 'Child work item statuses rolled up to blocked.'],
    'any in progress' => [['todo', 'todo'], 'start', 'todo', 'in_progress', 'Child work item statuses rolled up to in progress.'],
    'done and todo' => [['in_progress', 'todo'], 'complete', 'done', 'in_progress', 'Child work item statuses rolled up to in progress.'],
    'done and cancelled' => [['in_progress', 'cancelled'], 'complete', 'todo', 'done', 'All child work items are done.'],
    'cancelled and todo' => [['cancelled', 'todo'], 'reopen', 'done', 'todo', 'Child work item statuses rolled up to todo.'],
]);

it('propagates rollup changes through ancestors', function () {
    $root = WorkItem::create([
        'project_id' => $this->project->id,
        'kind' => 'work_package',
        'name' => 'Root',
        'status' => 'todo',
    ]);
    $parent = WorkItem::create([
        'project_id' => $this->project->id,
        'parent_id' => $root->id,
        'kind' => 'work_package',
        'name' => 'Parent',
        'status' => 'todo',
    ]);
    $child = WorkItem::create([
        'project_id' => $this->project->id,
        'parent_id' => $parent->id,
        'kind' => 'deliverable',
        'name' => 'Child',
        'status' => 'todo',
    ]);

    (new StartWorkItem)->apply($child, $this->user);

    expect($parent->fresh()->status)->toBe('in_progress')
        ->and($root->fresh()->status)->toBe('in_progress');

    (new CompleteWorkItem)->apply($child->fresh(), $this->user);

    expect($parent->fresh()->status)->toBe('done')
        ->and($root->fresh()->status)->toBe('done');

    (new ReopenWorkItem)->apply($child->fresh(), $this->user);

    expect($parent->fresh()->status)->toBe('todo')
        ->and($root->fresh()->status)->toBe('todo');
});

// ---- page access ----

it('404s the work item page for a user from another workspace', function () {
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

    $this->actingAs($this->user)
        ->get(route('work-items.show', $foreignItem))
        ->assertNotFound();
});
