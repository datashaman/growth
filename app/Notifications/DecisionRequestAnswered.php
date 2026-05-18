<?php

namespace App\Notifications;

use App\Models\DecisionRequest;
use Illuminate\Support\Str;

/**
 * Catalogue event `decision_request.answered`.
 *
 * Payload: an answered decision request and its chosen option.
 * Recipient: the user who raised the request.
 */
class DecisionRequestAnswered extends WorkspaceNotification
{
    public function __construct(private readonly DecisionRequest $decisionRequest) {}

    public function event(): string
    {
        return 'decision_request.answered';
    }

    public function title(): string
    {
        return 'Your decision request was answered';
    }

    public function body(): string
    {
        return sprintf(
            '“%s” — %s',
            Str::limit($this->decisionRequest->question, 100),
            $this->decisionRequest->chosenOption?->label ?? 'answered',
        );
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
