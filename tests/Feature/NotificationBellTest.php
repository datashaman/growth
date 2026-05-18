<?php

use App\Models\User;
use App\Notifications\ProjectStatusChanged;
use Illuminate\Support\Str;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    /**
     * Insert a database notification row for a user, returning its id.
     */
    $this->notify = function (User $user, string $title = 'Project status changed', ?string $readAt = null): string {
        $id = (string) Str::uuid();

        $user->notifications()->create([
            'id' => $id,
            'type' => ProjectStatusChanged::class,
            'data' => [
                'event' => 'project.status_changed',
                'title' => $title,
                'body' => 'Apollo is now active.',
                'url' => '/dashboard',
                'subject_type' => 'project',
                'subject_id' => 'p1',
                'workspace_id' => $user->active_workspace_id,
            ],
            'read_at' => $readAt,
        ]);

        return $id;
    };
});

test('the bell shows no unread indicator when there is nothing unread', function () {
    Livewire::test('notification-bell')
        ->assertDontSeeHtml('notification-indicator');
});

test('the bell shows the unread indicator when an unread notification exists', function () {
    ($this->notify)($this->user);

    Livewire::test('notification-bell')
        ->assertSeeHtml('notification-indicator')
        ->assertSee('Project status changed');
});

test('the bar variant shows the unread indicator with the unread count', function () {
    ($this->notify)($this->user);

    Livewire::test('notification-bell', ['variant' => 'bar'])
        ->assertSeeHtml('notification-indicator')
        ->assertSee('Project status changed');
});

test('marking a notification as read clears the indicator', function () {
    $id = ($this->notify)($this->user);

    Livewire::test('notification-bell')
        ->assertSeeHtml('notification-indicator')
        ->call('markAsRead', $id)
        ->assertDontSeeHtml('notification-indicator');

    expect($this->user->notifications()->find($id)->read_at)->not->toBeNull();
});

test('mark all as read clears every unread notification', function () {
    ($this->notify)($this->user, 'First');
    ($this->notify)($this->user, 'Second');

    Livewire::test('notification-bell')
        ->call('markAllAsRead')
        ->assertDontSeeHtml('notification-indicator');

    expect($this->user->unreadNotifications()->count())->toBe(0);
});

test('the bell only shows the current user\'s notifications', function () {
    $other = User::factory()->create();
    ($this->notify)($other, 'Someone else\'s notification');

    Livewire::test('notification-bell')
        ->assertDontSee('Someone else\'s notification')
        ->assertDontSeeHtml('notification-indicator');
});

test('the bell does not show notifications from another workspace', function () {
    $other = User::factory()->create();
    ($this->notify)($this->user, 'Cross-workspace leak');
    $this->user->notifications()->latest()->first()->update([
        'data->workspace_id' => $other->active_workspace_id,
    ]);

    Livewire::test('notification-bell')
        ->assertDontSee('Cross-workspace leak')
        ->assertDontSeeHtml('notification-indicator');
});

test('the bell subscribes to broadcast notifications for live arrival', function () {
    $listeners = Livewire::test('notification-bell')->instance()->getListeners();

    expect($listeners)->toHaveKey(
        'echo-private:App.Models.User.'.$this->user->id.',.Illuminate\\Notifications\\Events\\BroadcastNotificationCreated'
    );
});
