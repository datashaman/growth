<?php

namespace App\Growth\Transitions;

use App\Models\Project;
use App\Notifications\ProjectStatusChanged;
use App\Notifications\WorkspaceNotification;
use Illuminate\Database\Eloquent\Model;

/**
 * Close a project: `active` → `closed`.
 */
class CloseProject extends Transition
{
    public function allowedFrom(): array
    {
        return ['active'];
    }

    public function targetStatus(): string
    {
        return 'closed';
    }

    public function verb(): string
    {
        return 'close';
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
