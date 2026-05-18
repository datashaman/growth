<?php

namespace App\Growth\Transitions;

use App\Models\Project;
use App\Notifications\ProjectStatusChanged;
use App\Notifications\WorkspaceNotification;
use Illuminate\Database\Eloquent\Model;

/**
 * Restore an archived or closed project: `archived`/`closed` → `active`.
 */
class RestoreProject extends Transition
{
    public function allowedFrom(): array
    {
        return ['archived', 'closed'];
    }

    public function targetStatus(): string
    {
        return 'active';
    }

    public function verb(): string
    {
        return 'restore';
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
