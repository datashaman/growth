<?php

namespace App\Notifications;

use App\Models\FeedbackComment;
use Illuminate\Support\Str;

/**
 * Catalogue event `feedback.commented`.
 *
 * Payload: someone added a comment to a feedback thread.
 * Recipients: the other thread participants — the filer and anyone who has
 * previously commented — never the comment's own author.
 * Emitted by the comment-feedback tool.
 */
class FeedbackCommented extends WorkspaceNotification
{
    public function __construct(private readonly FeedbackComment $comment) {}

    public function event(): string
    {
        return 'feedback.commented';
    }

    public function title(): string
    {
        return sprintf('%s commented on feedback', $this->comment->author?->name ?? 'Someone');
    }

    public function body(): string
    {
        return Str::limit($this->comment->body, 140);
    }

    public function url(): ?string
    {
        return route('feedback.show', $this->comment->tool_feedback_id, false);
    }

    public function subject(): array
    {
        return ['feedback', (string) $this->comment->tool_feedback_id];
    }
}
