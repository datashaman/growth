<?php

namespace App\Notifications;

use App\Models\Project;

/**
 * Catalogue event `project.status_changed`.
 *
 * Payload: the project and its new status.
 * Recipients: every member of the project's workspace, minus the actor.
 * Emitted by the ActivateProject / ArchiveProject / CloseProject /
 * RestoreProject transitions.
 */
class ProjectStatusChanged extends WorkspaceNotification
{
    public function __construct(private readonly Project $project) {}

    public function event(): string
    {
        return 'project.status_changed';
    }

    public function title(): string
    {
        return 'Project status changed';
    }

    public function body(): string
    {
        return sprintf('“%s” is now %s.', $this->project->name, $this->project->status);
    }

    public function url(): ?string
    {
        return route('dashboard', ['project' => $this->project->id], false);
    }

    public function subject(): array
    {
        return ['project', $this->project->id];
    }
}
