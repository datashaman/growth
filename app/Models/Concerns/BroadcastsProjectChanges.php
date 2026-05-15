<?php

namespace App\Models\Concerns;

use App\Events\ProjectDataChanged;
use Illuminate\Database\Eloquent\Model;

/**
 * Broadcasts a ProjectDataChanged event on the model's project channel
 * whenever an instance is saved or deleted. The event implements
 * ShouldDispatchAfterCommit, so transactional writes broadcast a single
 * coherent snapshot.
 *
 * Note: bypasses Eloquent events (Model::where(...)->update() / ->delete())
 * will not dispatch — that is a known gap.
 */
trait BroadcastsProjectChanges
{
    protected static function bootBroadcastsProjectChanges(): void
    {
        static::saved(function (Model $model): void {
            $projectId = $model->getAttribute('project_id');

            if (filled($projectId)) {
                ProjectDataChanged::dispatch((string) $projectId);
            }
        });

        static::deleted(function (Model $model): void {
            $projectId = $model->getAttribute('project_id');

            if (filled($projectId)) {
                ProjectDataChanged::dispatch((string) $projectId);
            }
        });
    }
}
