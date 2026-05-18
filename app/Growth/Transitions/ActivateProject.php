<?php

namespace App\Growth\Transitions;

use App\Models\Project;
use App\Notifications\ProjectStatusChanged;
use App\Notifications\WorkspaceNotification;
use Illuminate\Database\Eloquent\Model;

/**
 * Activate a project: `draft` → `active`.
 */
class ActivateProject extends Transition
{
    public function allowedFrom(): array
    {
        return ['draft'];
    }

    public function targetStatus(): string
    {
        return 'active';
    }

    public function verb(): string
    {
        return 'activate';
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
