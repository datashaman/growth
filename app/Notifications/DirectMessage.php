<?php

namespace App\Notifications;

use App\Models\User;

/**
 * Catalogue event `direct.message`.
 *
 * Payload: a free-text message one workspace member addressed to another.
 * Recipients: the addressed user (a personal event — sent only to them).
 * Emitted by the send-notification MCP tool.
 */
class DirectMessage extends WorkspaceNotification
{
    public function __construct(
        private readonly User $sender,
        private readonly string $message,
    ) {}

    public function event(): string
    {
        return 'direct.message';
    }

    public function title(): string
    {
        return sprintf('Message from %s', $this->sender->name ?: $this->sender->email);
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
