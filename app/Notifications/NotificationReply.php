<?php

namespace App\Notifications;

use App\Models\User;

/**
 * Catalogue event `notification.reply`.
 *
 * Payload: a free-text reply one workspace member wrote to a notification
 * they received. Recipients: the original sender of that notification (a
 * personal event — sent only to them). The reply joins the originating
 * notification's thread so the inbox groups the exchange together.
 * Emitted by the reply-to-notification MCP tool.
 */
class NotificationReply extends WorkspaceNotification
{
    public function __construct(
        private readonly User $sender,
        private readonly string $message,
    ) {}

    public function event(): string
    {
        return 'notification.reply';
    }

    public function title(): string
    {
        return sprintf('Reply from %s', $this->sender->name ?: $this->sender->email);
    }

    public function body(): string
    {
        return $this->message;
    }

    public function url(): ?string
    {
        return null;
    }

    public function subject(): array
    {
        return ['user', (string) $this->sender->getKey()];
    }
}
