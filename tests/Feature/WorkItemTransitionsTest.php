<?php

use App\Growth\Transitions\CompleteWorkItem;
use App\Growth\Transitions\IllegalTransitionException;
use App\Growth\Transitions\StartWorkItem;
use App\Models\Project;
use App\Models\StatusTransition;
use App\Models\User;
use App\Models\WorkItem;
use Livewire\Livewire;

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

// ---- webapp buttons ----

it('shows a start button for a todo work item and starts it', function () {
    $item = ($this->makeItem)('todo');

    $this->actingAs($this->user);

    Livewire::test('pages::work-items.show', ['workItem' => $item])
        ->assertSee('Start')
        ->call('startWorkItem')
        ->assertHasNoErrors()
        ->assertDispatched('toast-show', dataset: ['variant' => 'success']);

    expect($item->fresh()->status)->toBe('in_progress');

    $transition = StatusTransition::query()->sole();
    expect($transition->to_status)->toBe('in_progress')
        ->and($transition->transitioned_by_user_id)->toBe($this->user->id);
});

it('shows a complete button for an in_progress work item and completes it', function () {
    $item = ($this->makeItem)('in_progress');

    $this->actingAs($this->user);

    Livewire::test('pages::work-items.show', ['workItem' => $item])
        ->assertSee('Complete')
        ->call('completeWorkItem')
        ->assertHasNoErrors()
        ->assertDispatched('toast-show', dataset: ['variant' => 'success']);

    expect($item->fresh()->status)->toBe('done')
        ->and(StatusTransition::query()->sole()->to_status)->toBe('done');
});

it('rejects an illegal transition from the webapp and warns the user', function () {
    $item = ($this->makeItem)('done');

    $this->actingAs($this->user);

    Livewire::test('pages::work-items.show', ['workItem' => $item])
        ->call('startWorkItem')
        ->assertHasNoErrors()
        ->assertDispatched('toast-show', dataset: ['variant' => 'danger']);

    expect($item->fresh()->status)->toBe('done')
        ->and(StatusTransition::count())->toBe(0);
});

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
