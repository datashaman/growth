<?php

namespace App\Models\Concerns;

use App\Events\ProjectDataChanged;
use Illuminate\Database\Eloquent\Model;

/**
 * For models whose owning project is reachable via a relation rather than
 * a direct project_id column (e.g. ReviewParticipant → review.project).
 * The model must define projectIdForBroadcast(): ?string.
 *
 * Mirrors BroadcastsProjectChanges but defers the id resolution to the
 * model instead of reading project_id directly. Dispatch is after-commit
 * via ProjectDataChanged.
 */
trait BroadcastsViaProjectRelation
{
    protected static function bootBroadcastsViaProjectRelation(): void
    {
        static::saved(function (Model $model): void {
            $projectId = $model->projectIdForBroadcast();

            if (filled($projectId)) {
                ProjectDataChanged::dispatch((string) $projectId);
            }
        });

        static::deleted(function (Model $model): void {
            $projectId = $model->projectIdForBroadcast();

            if (filled($projectId)) {
                ProjectDataChanged::dispatch((string) $projectId);
            }
        });
    }
}
