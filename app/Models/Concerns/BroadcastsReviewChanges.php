<?php

namespace App\Models\Concerns;

use App\Events\ReviewDataChanged;
use Illuminate\Database\Eloquent\Model;

/**
 * Broadcasts a ReviewDataChanged event on the model's review channel
 * whenever an instance is saved or deleted. Resolves the review id from
 * a reviewIdForBroadcast() method if present, otherwise from the review_id
 * attribute. ShouldDispatchAfterCommit so transactional writes broadcast
 * a single coherent snapshot.
 *
 * Use for review-detail-scoped surfaces (review findings, participants,
 * targets, decision events) so the review detail page only refreshes for
 * its own changes, not for unrelated project activity.
 */
trait BroadcastsReviewChanges
{
    protected static function bootBroadcastsReviewChanges(): void
    {
        $resolve = function (Model $model): mixed {
            return method_exists($model, 'reviewIdForBroadcast')
                ? $model->reviewIdForBroadcast()
                : $model->getAttribute('review_id');
        };

        static::saved(function (Model $model) use ($resolve): void {
            $reviewId = $resolve($model);

            if (filled($reviewId)) {
                ReviewDataChanged::dispatch((string) $reviewId);
            }
        });

        static::deleted(function (Model $model) use ($resolve): void {
            $reviewId = $resolve($model);

            if (filled($reviewId)) {
                ReviewDataChanged::dispatch((string) $reviewId);
            }
        });
    }
}
