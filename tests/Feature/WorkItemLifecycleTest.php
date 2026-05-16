<?php

use App\Models\Project;
use App\Models\StatusTransition;
use App\Models\User;
use App\Models\WorkItem;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
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

    $this->actingAs($this->user);
});

it('blocks a work item from the modal and records the reason', function () {
    $item = ($this->makeItem)('in_progress');

    Livewire::test('pages::work-items.show', ['workItem' => $item])
        ->set('blockReason', 'Waiting on review')
        ->call('blockWorkItem')
        ->assertHasNoErrors()
        ->assertDispatched('toast-show', dataset: ['variant' => 'success']);

    expect($item->fresh()->status)->toBe('blocked');

    $transition = StatusTransition::query()->sole();
    expect($transition->to_status)->toBe('blocked')
        ->and($transition->reason)->toBe('Waiting on review');
});

it('requires a blocker reason in the webapp', function () {
    $item = ($this->makeItem)('todo');

    Livewire::test('pages::work-items.show', ['workItem' => $item])
        ->set('blockReason', '')
        ->call('blockWorkItem')
        ->assertHasErrors('blockReason');

    expect($item->fresh()->status)->toBe('todo')
        ->and(StatusTransition::count())->toBe(0);
});

it('unblocks a blocked work item from the webapp', function () {
    $item = ($this->makeItem)('blocked');

    Livewire::test('pages::work-items.show', ['workItem' => $item])
        ->assertSee('Unblock')
        ->call('unblockWorkItem')
        ->assertDispatched('toast-show', dataset: ['variant' => 'success']);

    expect($item->fresh()->status)->toBe('in_progress');
});

it('cancels a work item from the webapp', function () {
    $item = ($this->makeItem)('todo');

    Livewire::test('pages::work-items.show', ['workItem' => $item])
        ->assertSee('Cancel work')
        ->call('cancelWorkItem')
        ->assertDispatched('toast-show', dataset: ['variant' => 'success']);

    expect($item->fresh()->status)->toBe('cancelled');
});

it('reopens a done work item from the webapp', function () {
    $item = ($this->makeItem)('done');

    Livewire::test('pages::work-items.show', ['workItem' => $item])
        ->assertSee('Reopen')
        ->call('reopenWorkItem')
        ->assertDispatched('toast-show', dataset: ['variant' => 'success']);

    expect($item->fresh()->status)->toBe('todo');
});

it('warns the user when a webapp transition is illegal', function () {
    $item = ($this->makeItem)('done');

    Livewire::test('pages::work-items.show', ['workItem' => $item])
        ->call('unblockWorkItem')
        ->assertDispatched('toast-show', dataset: ['variant' => 'danger']);

    expect($item->fresh()->status)->toBe('done')
        ->and(StatusTransition::count())->toBe(0);
});

it('shows only the transitions legal for the current status', function () {
    $blocked = ($this->makeItem)('blocked');

    Livewire::test('pages::work-items.show', ['workItem' => $blocked])
        ->assertSee('Unblock')
        ->assertSee('Cancel work')
        ->assertDontSee('Start')
        ->assertDontSee('Reopen');

    $cancelled = ($this->makeItem)('cancelled');

    Livewire::test('pages::work-items.show', ['workItem' => $cancelled])
        ->assertSee('Reopen')
        ->assertDontSee('Cancel work')
        ->assertDontSee('Unblock');
});
