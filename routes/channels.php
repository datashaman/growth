<?php

use App\Models\Project;
use App\Models\User;
use App\Models\WorkspaceMembership;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function (User $user, int $id): bool {
    return $user->id === $id;
});

Broadcast::channel('workspaces.{workspaceId}', function (User $user, string $workspaceId): bool {
    return WorkspaceMembership::query()
        ->where('workspace_id', $workspaceId)
        ->where('user_id', $user->id)
        ->exists();
});

Broadcast::channel('projects.{projectId}', function (User $user, string $projectId): bool {
    $workspaceId = Project::query()->whereKey($projectId)->value('workspace_id');

    if ($workspaceId === null) {
        return false;
    }

    return WorkspaceMembership::query()
        ->where('workspace_id', $workspaceId)
        ->where('user_id', $user->id)
        ->exists();
});
