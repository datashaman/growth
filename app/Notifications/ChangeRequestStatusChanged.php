<?php

namespace App\Notifications;

use App\Growth\Transitions\ChangeRequestTransition;
use App\Models\ChangeRequest;

/**
 * Catalogue event `change_request.status_changed`.
 *
 * Payload: a change request and the status it moved between.
 * Recipients: every user subscribed to the change request, minus the actor.
 * Emitted by every {@see ChangeRequestTransition}.
 */
class ChangeRequestStatusChanged extends WorkspaceNotification
{
    public function __construct(
        private readonly ChangeRequest $changeRequest,
        private readonly string $fromStatus,
        private readonly string $toStatus,
    ) {}

    public function event(): string
    {
        return 'change_request.status_changed';
    }

    public function title(): string
    {
        return sprintf('%s status changed', $this->changeRequest->reference());
    }

    public function body(): string
    {
        return sprintf(
            '“%s” moved from %s to %s.',
            $this->changeRequest->title,
            str_replace('_', ' ', $this->fromStatus),
            str_replace('_', ' ', $this->toStatus),
        );
    }

    public function url(): ?string
    {
        return route('change-requests.show', $this->changeRequest->id, false);
    }

    public function subject(): array
    {
        return ['change_request', $this->changeRequest->id];
    }
}
