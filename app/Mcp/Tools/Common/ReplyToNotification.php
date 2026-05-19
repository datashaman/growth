<?php

namespace App\Mcp\Tools\Common;

use App\Models\User;
use App\Notifications\NotificationReply;
use App\Notifications\WorkspaceNotifier;
use App\Support\WorkspaceContext;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Reply to a notification in your inbox. The reply is delivered to whoever sent the original notification and joins its thread, so both sides see the exchange grouped together. Use list-notifications to find the notification_id.')]
class ReplyToNotification extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $sender = auth()->user();

        if (! $sender instanceof User) {
            return new ResponseFactory(Response::error('Replying to a notification needs an authenticated user.'));
        }

        $workspaceId = app(WorkspaceContext::class)->requireId();

        $data = $request->validate([
            'notification_id' => 'required|string',
            'message' => 'required|string|max:2000',
        ]);

        $notification = $sender->notifications()
            ->where('data->workspace_id', $workspaceId)
            ->whereKey($data['notification_id'])
            ->first();

        if ($notification === null) {
            return new ResponseFactory(Response::error('That notification is not in your inbox.'));
        }

        $originalSenderId = $notification->data['sender']['id'] ?? null;

        if ($originalSenderId === null) {
            return new ResponseFactory(Response::error('That notification has no sender to reply to — it was a system event.'));
        }

        $recipient = User::find($originalSenderId);

        if ($recipient === null) {
            return new ResponseFactory(Response::error('The original sender is no longer available.'));
        }

        $threadId = $notification->data['thread_id'] ?? $notification->getKey();

        app(WorkspaceNotifier::class)->notifyUser(
            $recipient,
            (new NotificationReply($sender, $data['message']))->inThread((string) $threadId),
        );

        return Response::structured([
            'thread_id' => (string) $threadId,
            'recipient_id' => $recipient->getKey(),
            'replied' => true,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'notification_id' => $schema->string()
                ->description('Id of the notification in your inbox to reply to. From list-notifications.')
                ->required(),
            'message' => $schema->string()
                ->description('The reply to deliver, max 2000 characters.')
                ->required(),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'thread_id' => $schema->string()->description('Thread the reply joined')->required(),
            'recipient_id' => $schema->integer()->description('User the reply was delivered to')->required(),
            'replied' => $schema->boolean()->required(),
        ];
    }
}
