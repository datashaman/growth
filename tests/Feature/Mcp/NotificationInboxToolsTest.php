<?php

use App\Mcp\Servers\ReadonlyServer;
use App\Mcp\Tools\Common\ListNotifications;
use App\Mcp\Tools\Common\MarkNotificationRead;
use App\Models\User;
use App\Notifications\DirectMessage;
use App\Notifications\WorkspaceNotifier;
use App\Support\WorkspaceContext;
use Illuminate\Support\Str;
use Laravel\Passport\Passport;

beforeEach(function () {
    $this->user = User::factory()->create();
    Passport::actingAs($this->user, ['mcp:use']);
    $this->workspaceId = $this->user->active_workspace_id;

    $this->makeNotification = fn (array $data = [], ?string $readAt = null, ?string $createdAt = null) => $this->user->notifications()->create([
        'id' => (string) Str::uuid(),
        'type' => DirectMessage::class,
        'read_at' => $readAt,
        'created_at' => $createdAt ?? now(),
        'data' => array_merge([
            'event' => 'direct.message',
            'title' => 'Message from Alice',
            'body' => 'Hello there',
            'url' => null,
            'subject_type' => 'user',
            'subject_id' => '2',
            'workspace_id' => $this->workspaceId,
            'sender' => ['id' => '2', 'name' => 'Alice'],
            'acting_role' => 'governance',
        ], $data),
    ]);
});

it('lists the caller notifications newest first with sender provenance', function () {
    ($this->makeNotification)(['title' => 'Older'], createdAt: now()->subHour()->toDateTimeString());
    ($this->makeNotification)(['title' => 'Newer']);

    ReadonlyServer::tool(ListNotifications::class, [])
        ->assertOk()
        ->assertStructuredContent(function ($json) {
            $json->where('total', 2)
                ->where('results.0.title', 'Newer')
                ->where('results.0.sender.name', 'Alice')
                ->where('results.0.acting_role', 'governance')
                ->where('results.0.read', false)
                ->etc();
        });
});

it('filters to unread notifications', function () {
    ($this->makeNotification)(['title' => 'Unread one']);
    ($this->makeNotification)(['title' => 'Already read'], readAt: now()->toDateTimeString());

    ReadonlyServer::tool(ListNotifications::class, ['unread_only' => true])
        ->assertOk()
        ->assertStructuredContent(function ($json) {
            $json->where('total', 1)
                ->where('results.0.title', 'Unread one')
                ->etc();
        });
});

it('pages results with limit and offset', function () {
    ($this->makeNotification)(['title' => 'Oldest'], createdAt: now()->subHours(2)->toDateTimeString());
    ($this->makeNotification)(['title' => 'Middle'], createdAt: now()->subHour()->toDateTimeString());
    ($this->makeNotification)(['title' => 'Newest']);

    ReadonlyServer::tool(ListNotifications::class, ['limit' => 1, 'offset' => 1])
        ->assertOk()
        ->assertStructuredContent(function ($json) {
            $json->where('total', 3)
                ->where('limit', 1)
                ->where('offset', 1)
                ->where('results.0.title', 'Middle')
                ->count('results', 1)
                ->etc();
        });
});

it('does not list notifications from another workspace', function () {
    $other = User::factory()->create();
    ($this->makeNotification)(['workspace_id' => $other->active_workspace_id]);

    ReadonlyServer::tool(ListNotifications::class, [])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json->where('total', 0)->etc());
});

it('marks a single notification read', function () {
    $notification = ($this->makeNotification)();
    ($this->makeNotification)();

    ReadonlyServer::tool(MarkNotificationRead::class, ['notification_id' => $notification->id])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json->where('marked', 1)->etc());

    expect($this->user->notifications()->whereNull('read_at')->count())->toBe(1);
});

it('marks every unread notification read when no id is given', function () {
    ($this->makeNotification)();
    ($this->makeNotification)();
    ($this->makeNotification)([], readAt: now()->toDateTimeString());

    ReadonlyServer::tool(MarkNotificationRead::class, [])
        ->assertOk()
        // Only the two unread are newly marked; the already-read one is left alone.
        ->assertStructuredContent(fn ($json) => $json->where('marked', 2)->etc());

    expect($this->user->notifications()->whereNull('read_at')->count())->toBe(0);
});

it('marking an already-read notification is idempotent', function () {
    $notification = ($this->makeNotification)([], readAt: now()->toDateTimeString());

    ReadonlyServer::tool(MarkNotificationRead::class, ['notification_id' => $notification->id])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json->where('marked', 0)->etc());
});

it('stamps workspace, sender, and acting role onto a sent notification', function () {
    app(WorkspaceContext::class)->set($this->workspaceId);

    app(WorkspaceNotifier::class)->notifyUser($this->user, new DirectMessage($this->user, 'Ping'));

    ReadonlyServer::tool(ListNotifications::class, [])
        ->assertOk()
        ->assertStructuredContent(function ($json) {
            $json->where('total', 1)
                ->where('results.0.sender.name', $this->user->name)
                ->etc();
        });
});
