<?php

namespace App\Mcp\Prompts\Concerns;

use App\Models\Project;
use App\Support\WorkspaceContext;
use Laravel\Mcp\Server\Completions\CompletionResponse;
use Laravel\Mcp\Server\Contracts\Completable;

/**
 * Satisfies the {@see Completable} contract for a prompt whose only
 * completable argument is `project_id`.
 *
 * Completion values are the ULIDs of every project in the active workspace;
 * the MCP client filters them by the prefix the user has typed. Any other
 * argument yields an empty response.
 */
trait CompletesProjectId
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function complete(string $argument, string $value, array $context): CompletionResponse
    {
        if ($argument !== 'project_id') {
            return CompletionResponse::empty();
        }

        $workspaceId = app(WorkspaceContext::class)->id();

        if ($workspaceId === null) {
            return CompletionResponse::empty();
        }

        $ids = Project::query()
            ->where('workspace_id', $workspaceId)
            ->orderBy('name')
            ->pluck('id')
            ->all();

        return CompletionResponse::match($ids);
    }
}
