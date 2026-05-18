<?php

namespace App\Notifications;

use App\Models\Review;

/**
 * Catalogue event `review.held`.
 *
 * Payload: the review that was held.
 * Recipients: every member of the project's workspace, minus the actor.
 * Emitted by the HoldReview transition.
 */
class ReviewHeld extends WorkspaceNotification
{
    public function __construct(private readonly Review $review) {}

    public function event(): string
    {
        return 'review.held';
    }

    public function title(): string
    {
        return 'Review held';
    }

    public function body(): string
    {
        return sprintf('“%s” was held.', $this->review->title);
    }

    public function url(): ?string
    {
        return route('reviews.show', $this->review->id, false);
    }

    public function subject(): array
    {
        return ['review', $this->review->id];
    }
}
