<?php

use App\Growth\Transitions\CompleteWorkItem;
use App\Growth\Transitions\IllegalTransitionException;
use App\Growth\Transitions\StartWorkItem;
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

    expect($parent->fresh()->status)->toBe('todo');
    expect(StatusTransition::query()->where('transitionable_id', $parent->id)->exists())->toBeFalse();
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
