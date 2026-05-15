<?php

namespace App\Models\Concerns;

use App\Events\WorkspaceDataChanged;
use Illuminate\Database\Eloquent\Model;

/**
 * Broadcasts a WorkspaceDataChanged event on the model's workspace channel
 * whenever an instance is saved or deleted. The event implements
 * ShouldDispatchAfterCommit so transactional writes broadcast a single
 * coherent snapshot.
 *
 * Mirror of BroadcastsProjectChanges for workspace-scoped models (e.g. ToolInvocation).
 */
trait BroadcastsWorkspaceChanges
{
    protected static function bootBroadcastsWorkspaceChanges(): void
    {
        static::saved(function (Model $model): void {
            $workspaceId = $model->getAttribute('workspace_id');

            if (filled($workspaceId)) {
                WorkspaceDataChanged::dispatch((string) $workspaceId);
            }
        });

        static::deleted(function (Model $model): void {
            $workspaceId = $model->getAttribute('workspace_id');

            if (filled($workspaceId)) {
                WorkspaceDataChanged::dispatch((string) $workspaceId);
            }
        });
    }
}
