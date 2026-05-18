<?php

namespace App\Notifications;

use App\Models\Project;
use App\Models\User;
use App\Models\Workspace;
use App\Support\RoleContext;
use App\Support\WorkspaceContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Notification;

/**
 * Fans a {@see WorkspaceNotification} out to its recipients.
 *
 * Two recipient shapes cover the catalogue: workspace-wide events go to
 * every member of the subject's workspace (minus the actor who caused
 * them), and personal events go to a single named user.
 */
class WorkspaceNotifier
{
    /**
     * Send to every member of the subject's workspace, skipping the actor.
     *
     * No-op when the subject cannot be resolved to a workspace.
     */
    public function notifyWorkspace(Model $subject, WorkspaceNotification $notification, ?User $actor = null): void
    {
        $workspace = $this->workspaceOf($subject);

        if ($workspace === null) {
            return;
        }

        $recipients = $workspace->members
            ->reject(fn (User $member): bool => $actor !== null && $member->is($actor))
            ->values();

        if ($recipients->isNotEmpty()) {
            $notification->withContext($workspace->id, $actor, $this->actingRole());
            Notification::send($recipients, $notification);
        }
    }

    /**
     * Send to a single user — used for personal events. The sender is the
     * authenticated user (the actor of the request that triggered it).
     */
    public function notifyUser(User $user, WorkspaceNotification $notification): void
    {
        $notification->withContext(
            app(WorkspaceContext::class)->id(),
            auth()->user(),
            $this->actingRole(),
        );

        $user->notify($notification);
    }

    /**
     * The operating role bound to the current session, as its stored value.
     */
    private function actingRole(): ?string
    {
        return app(RoleContext::class)->role()?->value;
    }

    /**
     * Resolve the workspace a notification subject belongs to. Projects carry
     * the workspace directly; every other catalogue subject reaches it
     * through its `project` relation.
     */
    private function workspaceOf(Model $subject): ?Workspace
    {
        $project = $subject instanceof Project ? $subject : $subject->project;

        return $project?->workspace;
    }
}
