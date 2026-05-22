<?php

use App\Events\WorkspaceDataChanged;
use App\Models\ToolFeedback;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    $this->makeFeedback = fn (array $attributes = []): ToolFeedback => ToolFeedback::create(array_merge([
        'workspace_id' => $this->user->active_workspace_id,
        'user_id' => $this->user->id,
        'category' => 'difficulty',
        'status' => 'new',
        'summary' => 'Generic summary',
        'body' => 'Generic body',
    ], $attributes));
});

test('saving feedback dispatches WorkspaceDataChanged on its workspace channel', function () {
    Event::fake([WorkspaceDataChanged::class]);

    ($this->makeFeedback)();

    Event::assertDispatched(
        WorkspaceDataChanged::class,
        fn (WorkspaceDataChanged $e) => $e->workspaceId === (string) $this->user->active_workspace_id,
    );
});

test('the inbox renders feedback and refreshes on broadcast', function () {
    ($this->makeFeedback)(['summary' => 'Confusing schema']);

    $component = Livewire::test('pages::feedback')->assertSee('Confusing schema');

    ($this->makeFeedback)(['summary' => 'Missing bulk tool']);

    $component
        ->call('onWorkspaceDataChanged')
        ->assertSee('Missing bulk tool');
});

test('the inbox keeps caller metadata on one line', function () {
    ($this->makeFeedback)(['summary' => 'Confusing schema']);

    Livewire::test('pages::feedback')
        ->assertSee('Caller')
        ->assertSee($this->user->name)
        ->assertSee('whitespace-nowrap', false);
});

test('the inbox only shows the active workspace feedback', function () {
    ($this->makeFeedback)(['summary' => 'Local feedback']);

    $other = User::factory()->create();
    ToolFeedback::create([
        'workspace_id' => $other->active_workspace_id,
        'user_id' => $other->id,
        'category' => 'difficulty',
        'status' => 'new',
        'summary' => 'Foreign feedback',
        'body' => 'Body',
    ]);

    Livewire::test('pages::feedback')
        ->assertSee('Local feedback')
        ->assertDontSee('Foreign feedback');
});

test('the inbox renders timestamps for browser-local formatting', function () {
    ($this->makeFeedback)(['summary' => 'Timed feedback']);

    Livewire::test('pages::feedback')
        ->assertSee('Timed feedback')
        ->assertSee('data-local-time', false)
        ->assertSee('datetime=', false);
});

test('the inbox hides resolved feedback by default and reveals it via the status filter', function () {
    ($this->makeFeedback)(['status' => 'new', 'summary' => 'Open item']);
    ($this->makeFeedback)(['status' => 'resolved', 'summary' => 'Resolved item']);

    Livewire::test('pages::feedback')
        ->assertSee('Open item')
        ->assertDontSee('Resolved item')
        ->set('statusFilter', 'resolved')
        ->assertSee('Resolved item')
        ->assertDontSee('Open item')
        ->set('statusFilter', 'all')
        ->assertSee('Open item')
        ->assertSee('Resolved item');
});

test('the inbox renders its status filter inside the table card header', function () {
    ($this->makeFeedback)(['summary' => 'Open item']);

    Livewire::test('pages::feedback')
        ->assertSee('recent')
        ->assertSee('data-test="feedback-status-filter"', false)
        ->assertSeeInOrder(['Feedback', 'recent']);
});

test('the listener is empty when the user has no active workspace', function () {
    $orphan = User::factory()->create();
    $orphan->forceFill(['active_workspace_id' => null])->save();
    $this->actingAs($orphan);

    $listeners = Livewire::test('pages::feedback')->instance()->getListeners();

    expect($listeners)->toBe([]);
});
