<?php

namespace App\Notifications;

use App\Models\DecisionRequest;
use Illuminate\Support\Str;

/**
 * Catalogue event `decision_request.raised`.
 *
 * Payload: a newly raised decision request.
 * Recipients: every user assigned to the target role, minus the requester.
 */
class DecisionRequestRaised extends WorkspaceNotification
{
    public function __construct(private readonly DecisionRequest $decisionRequest) {}

    public function event(): string
    {
        return 'decision_request.raised';
    }

    public function title(): string
    {
        return sprintf('Decision requested from %s', $this->decisionRequest->targetRole->name);
    }

    public function body(): string
    {
        return Str::limit($this->decisionRequest->question, 140);
    }

    public function url(): ?string
    {
        return null;
    }

    public function subject(): array
    {
        return ['decision_request', $this->decisionRequest->id];
    }
}
