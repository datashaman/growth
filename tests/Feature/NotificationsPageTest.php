<?php

use App\Models\User;
use App\Notifications\DirectMessage;
use Illuminate\Support\Str;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->workspaceId = $this->user->active_workspace_id;

    $this->makeNotification = fn (array $data = [], ?string $readAt = null) => $this->user->notifications()->create([
        'id' => (string) Str::uuid(),
        'type' => DirectMessage::class,
        'read_at' => $readAt,
        'data' => array_merge([
            'event' => 'direct.message',
            'title' => 'Message from Alice',
            'body' => 'Hello there',
            'url' => null,
            'subject_type' => 'user',
            'subject_id' => '2',
            'workspace_id' => $this->workspaceId,
            'sender' => ['id' => '2', 'name' => 'Alice'],
            'acting_surface' => 'governance',
        ], $data),
    ]);
});

it('serves the notifications page', function () {
    $this->actingAs($this->user)
        ->get(route('notifications'))
        ->assertOk();
});

it('renders the caller notifications with sender and acting role', function () {
    ($this->makeNotification)(['title' => 'Mockup ready']);

    Livewire::actingAs($this->user)
        ->test('pages::notifications')
        ->assertSee('Mockup ready')
        ->assertSee('Alice')
        ->assertSee('governance');
});

it('does not show notifications from another workspace', function () {
    $other = User::factory()->create();
    ($this->makeNotification)(['title' => 'Cross-workspace leak', 'workspace_id' => $other->active_workspace_id]);

    Livewire::actingAs($this->user)
        ->test('pages::notifications')
        ->assertDontSee('Cross-workspace leak');
});

it('marks an unthreaded notification read by its own id', function () {
    $notification = ($this->makeNotification)();

    Livewire::actingAs($this->user)
        ->test('pages::notifications')
        ->call('markThreadRead', $notification->id);

    expect($this->user->notifications()->whereNull('read_at')->count())->toBe(0);
});

it('groups a thread together and marks the whole thread read at once', function () {
    $threadId = (string) Str::uuid();
    ($this->makeNotification)(['title' => 'Original question', 'thread_id' => $threadId]);
    ($this->makeNotification)(['title' => 'Threaded reply', 'thread_id' => $threadId, 'event' => 'notification.reply']);
    ($this->makeNotification)(['title' => 'Unrelated message', 'thread_id' => (string) Str::uuid()]);

    $component = Livewire::actingAs($this->user)
        ->test('pages::notifications')
        ->assertSee('Original question')
        ->assertSee('Threaded reply')
        ->assertSee('Unrelated message');

    // Two threads: the two-message exchange and the lone message.
    expect($component->instance()->threads)->toHaveCount(2);

    $component->call('markThreadRead', $threadId);

    expect($this->user->notifications()->whereNull('read_at')->count())->toBe(1);
});

it('marks every notification read', function () {
    ($this->makeNotification)();
    ($this->makeNotification)();

    Livewire::actingAs($this->user)
        ->test('pages::notifications')
        ->call('markAllRead');

    expect($this->user->notifications()->whereNull('read_at')->count())->toBe(0);
});
