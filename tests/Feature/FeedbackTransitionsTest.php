<?php

use App\Growth\Transitions\IllegalTransitionException;
use App\Growth\Transitions\ReopenFeedback;
use App\Growth\Transitions\ResolveFeedback;
use App\Growth\Transitions\TriageFeedback;
use App\Models\StatusTransition;
use App\Models\ToolFeedback;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();

    $this->makeFeedback = fn (string $status): ToolFeedback => ToolFeedback::create([
        'workspace_id' => $this->user->active_workspace_id,
        'user_id' => $this->user->id,
        'category' => 'bug',
        'status' => $status,
        'summary' => 'list-risks returns a 500',
        'body' => 'Calling list-risks without a project_id throws.',
    ]);
});

// ---- transition classes ----

it('applies a legal feedback transition and records an audit row', function () {
    $feedback = ($this->makeFeedback)('new');

    $transition = (new TriageFeedback)->apply($feedback, $this->user, 'Looking into it');

    expect($feedback->fresh()->status)->toBe('triaged')
        ->and($transition->from_status)->toBe('new')
        ->and($transition->to_status)->toBe('triaged')
        ->and($transition->reason)->toBe('Looking into it')
        ->and($transition->transitioned_by_user_id)->toBe($this->user->id)
        ->and(StatusTransition::count())->toBe(1);
});

it('rejects an illegal feedback source state without writing an audit row', function () {
    $feedback = ($this->makeFeedback)('new');

    expect(fn () => (new ReopenFeedback)->apply($feedback))
        ->toThrow(IllegalTransitionException::class);

    expect($feedback->fresh()->status)->toBe('new')
        ->and(StatusTransition::count())->toBe(0);
});

it('resolves feedback directly from new or via triaged', function () {
    $fromNew = ($this->makeFeedback)('new');
    $fromTriaged = ($this->makeFeedback)('triaged');

    (new ResolveFeedback)->apply($fromNew);
    (new ResolveFeedback)->apply($fromTriaged);

    expect($fromNew->fresh()->status)->toBe('resolved')
        ->and($fromTriaged->fresh()->status)->toBe('resolved');
});

// ---- webapp buttons ----

it('triages feedback from the inbox', function () {
    $feedback = ($this->makeFeedback)('new');

    $this->actingAs($this->user);

    Livewire::test('pages::feedback')
        ->assertSee('list-risks returns a 500')
        ->call('triage', $feedback->id)
        ->assertHasNoErrors()
        ->assertDispatched('toast-show', dataset: ['variant' => 'success']);

    expect($feedback->fresh()->status)->toBe('triaged');

    $transition = StatusTransition::query()->sole();
    expect($transition->to_status)->toBe('triaged')
        ->and($transition->transitionable->is($feedback))->toBeTrue();
});

it('walks feedback through resolve and reopen from the inbox', function () {
    $feedback = ($this->makeFeedback)('triaged');

    $this->actingAs($this->user);

    Livewire::test('pages::feedback')
        ->call('resolve', $feedback->id)
        ->assertDispatched('toast-show', dataset: ['variant' => 'success']);
    expect($feedback->fresh()->status)->toBe('resolved');

    Livewire::test('pages::feedback')
        ->call('reopen', $feedback->id)
        ->assertDispatched('toast-show', dataset: ['variant' => 'success']);
    expect($feedback->fresh()->status)->toBe('new');
});

it('warns the user on an illegal transition from the inbox', function () {
    $feedback = ($this->makeFeedback)('new');

    $this->actingAs($this->user);

    Livewire::test('pages::feedback')
        ->call('reopen', $feedback->id)
        ->assertHasNoErrors()
        ->assertDispatched('toast-show', dataset: ['variant' => 'danger']);

    expect($feedback->fresh()->status)->toBe('new')
        ->and(StatusTransition::count())->toBe(0);
});

it('404s when transitioning feedback from another workspace', function () {
    $stranger = User::factory()->create();
    $foreign = ToolFeedback::create([
        'workspace_id' => $stranger->active_workspace_id,
        'user_id' => $stranger->id,
        'category' => 'bug',
        'status' => 'new',
        'summary' => 'Off limits',
        'body' => 'Body.',
    ]);

    $this->actingAs($this->user);

    Livewire::test('pages::feedback')
        ->call('triage', $foreign->id)
        ->assertStatus(404);

    expect($foreign->fresh()->status)->toBe('new');
});
