<?php

namespace App\Notifications;

use App\Models\ToolFeedback;

/**
 * Catalogue event `feedback.status_changed`.
 *
 * Payload: a piece of feedback moved to a new status — triaged, resolved,
 * or reopened.
 * Recipients: the user who filed it — a personal event sent only to them.
 * Emitted by the feedback status transitions.
 */
class FeedbackStatusChanged extends WorkspaceNotification
{
    public function __construct(private readonly ToolFeedback $feedback) {}

    public function event(): string
    {
        return 'feedback.status_changed';
    }

    public function title(): string
    {
        return sprintf('Feedback %s', $this->statusLabel());
    }

    public function body(): string
    {
        return $this->feedback->summary;
    }

    public function url(): ?string
    {
        return route('feedback', [], false);
    }

    public function subject(): array
    {
        return ['feedback', (string) $this->feedback->getKey()];
    }

    /**
     * Human phrasing for the feedback's new status. Reopened feedback lands
     * back in `new`, but "reopened" reads more clearly to the filer.
     */
    private function statusLabel(): string
    {
        return match ($this->feedback->status) {
            'new' => 'reopened',
            default => $this->feedback->status,
        };
    }
}
