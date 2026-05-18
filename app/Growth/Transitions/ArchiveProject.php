<?php

namespace App\Growth\Transitions;

use App\Models\Project;
use App\Notifications\ProjectStatusChanged;
use App\Notifications\WorkspaceNotification;
use Illuminate\Database\Eloquent\Model;

/**
 * Archive a project: `active` → `archived`.
 */
class ArchiveProject extends Transition
{
    public function allowedFrom(): array
    {
        return ['active'];
    }

    public function targetStatus(): string
    {
        return 'archived';
    }

    public function verb(): string
    {
        return 'archive';
    }

    public function subjectLabel(): string
    {
        return 'project';
    }

    protected function notification(Model $subject): ?WorkspaceNotification
    {
        /** @var Project $subject */
        return new ProjectStatusChanged($subject);
    }
}
