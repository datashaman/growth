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

test('the listener is empty when the user has no active workspace', function () {
    $orphan = User::factory()->create();
    $orphan->forceFill(['active_workspace_id' => null])->save();
    $this->actingAs($orphan);

    $listeners = Livewire::test('pages::feedback')->instance()->getListeners();

    expect($listeners)->toBe([]);
});
