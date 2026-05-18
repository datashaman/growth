<?php

namespace App\Notifications;

use App\Models\ChangeRequest;

/**
 * Catalogue event `change_request.decided`.
 *
 * Payload: the change request and its recorded decision.
 * Recipients: every member of the project's workspace, minus the actor.
 * Emitted by the ApproveChangeRequest / RejectChangeRequest /
 * DeferChangeRequest transitions.
 */
class ChangeRequestDecided extends WorkspaceNotification
{
    public function __construct(private readonly ChangeRequest $changeRequest) {}

    public function event(): string
    {
        return 'change_request.decided';
    }

    public function title(): string
    {
        return 'Change request decided';
    }

    public function body(): string
    {
        return sprintf('“%s” was %s.', $this->changeRequest->title, $this->changeRequest->decision);
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
