<?php

use App\Mcp\Servers\ManagementServer;
use App\Mcp\Tools\Common\ReplyToNotification;
use App\Models\User;
use App\Notifications\DirectMessage;
use App\Notifications\NotificationReply;
use Illuminate\Support\Str;
use Laravel\Passport\Passport;

beforeEach(function () {
    $this->actor = User::factory()->create();
    Passport::actingAs($this->actor, ['mcp:use']);
    $this->workspaceId = $this->actor->active_workspace_id;

    $this->originalSender = User::factory()->create();

    /**
     * Drop a notification into the actor's inbox as if {@see $originalSender}
     * had sent it. Returns the stored row.
     */
    $this->inboxNotification = fn (array $data = []) => $this->actor->notifications()->create([
        'id' => (string) Str::uuid(),
        'type' => DirectMessage::class,
        'read_at' => null,
        'data' => array_merge([
            'event' => 'direct.message',
            'title' => 'Message from the sender',
            'body' => 'Original message',
            'url' => null,
            'subject_type' => 'user',
            'subject_id' => (string) $this->originalSender->id,
            'workspace_id' => $this->workspaceId,
            'thread_id' => (string) Str::uuid(),
            'sender' => ['id' => (string) $this->originalSender->id, 'name' => 'The Sender'],
            'acting_role' => null,
        ], $data),
    ]);
});

it('delivers a reply to the original sender and joins the thread', function () {
    $notification = ($this->inboxNotification)();
    $threadId = $notification->data['thread_id'];

    ManagementServer::tool(ReplyToNotification::class, [
        'notification_id' => $notification->id,
        'message' => 'Thanks — looking at it now.',
    ])
        ->assertOk()
        ->assertStructuredContent(function ($json) use ($threadId) {
            $json->where('thread_id', $threadId)
                ->where('recipient_id', $this->originalSender->id)
                ->where('replied', true)
                ->etc();
        });

    $reply = $this->originalSender->notifications()->sole();

    expect($reply->data['event'])->toBe('notification.reply')
        ->and($reply->data['body'])->toBe('Thanks — looking at it now.')
        ->and($reply->data['thread_id'])->toBe($threadId)
        ->and($reply->data['sender']['id'])->toBe((string) $this->actor->id);
});

it('falls back to the parent notification id when it carries no thread', function () {
    $notification = ($this->inboxNotification)(['thread_id' => null]);

    ManagementServer::tool(ReplyToNotification::class, [
        'notification_id' => $notification->id,
        'message' => 'Replying to an unthreaded notification.',
    ])
        ->assertOk()
        ->assertStructuredContent(function ($json) use ($notification) {
            $json->where('thread_id', $notification->id)->etc();
        });

    expect($this->originalSender->notifications()->sole()->data['thread_id'])
        ->toBe($notification->id);
});

it('rejects replying to a notification that is not in the caller inbox', function () {
    $stranger = User::factory()->create();
    $notification = $stranger->notifications()->create([
        'id' => (string) Str::uuid(),
        'type' => DirectMessage::class,
        'data' => [
            'event' => 'direct.message',
            'workspace_id' => $this->workspaceId,
            'thread_id' => (string) Str::uuid(),
            'sender' => ['id' => (string) $this->originalSender->id, 'name' => 'The Sender'],
        ],
    ]);

    ManagementServer::tool(ReplyToNotification::class, [
        'notification_id' => $notification->id,
        'message' => 'I should not be able to reply here.',
    ])->assertHasErrors();

    expect($this->originalSender->notifications()->count())->toBe(0);
});

it('rejects replying to a system notification with no sender', function () {
    $notification = ($this->inboxNotification)(['sender' => null]);

    ManagementServer::tool(ReplyToNotification::class, [
        'notification_id' => $notification->id,
        'message' => 'There is nobody to reply to.',
    ])->assertHasErrors();
});

it('records the reply sender as the authenticated caller', function () {
    $notification = ($this->inboxNotification)();

    ManagementServer::tool(ReplyToNotification::class, [
        'notification_id' => $notification->id,
        'message' => 'Provenance check.',
    ])->assertOk();

    $reply = $this->originalSender->notifications()->sole();

    expect($reply->type)->toBe(NotificationReply::class)
        ->and($reply->data['sender']['name'])->toBe($this->actor->name ?: $this->actor->email);
});
