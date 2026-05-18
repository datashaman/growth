<?php

namespace App\Growth\Transitions;

use App\Models\Review;
use App\Notifications\ReviewHeld as ReviewHeldNotification;
use App\Notifications\WorkspaceNotification;
use Illuminate\Database\Eloquent\Model;

/**
 * Record a review as held: `in_progress` → `held`.
 */
class HoldReview extends ReviewTransition
{
    public function allowedFrom(): array
    {
        return ['in_progress'];
    }

    public function targetStatus(): string
    {
        return 'held';
    }

    public function verb(): string
    {
        return 'hold';
    }

    protected function notification(Model $subject): ?WorkspaceNotification
    {
        /** @var Review $subject */
        return new ReviewHeldNotification($subject);
    }
}
