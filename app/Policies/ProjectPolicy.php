<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;
use App\Models\WorkspaceMembership;

class ProjectPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Project $project): bool
    {
        return $this->membership($user, $project) !== null;
    }

    public function create(User $user): bool
    {
        return $user->workspaceMemberships()
            ->whereIn('role', [
                WorkspaceMembership::ROLE_OWNER,
                WorkspaceMembership::ROLE_ADMIN,
                WorkspaceMembership::ROLE_MEMBER,
            ])
            ->exists();
    }

    public function update(User $user, Project $project): bool
    {
        return $this->canMutate($user, $project);
    }

    public function delete(User $user, Project $project): bool
    {
        $membership = $this->membership($user, $project);

        return $membership !== null && in_array(
            $membership->role,
            [WorkspaceMembership::ROLE_OWNER, WorkspaceMembership::ROLE_ADMIN],
            true,
        );
    }

    private function canMutate(User $user, Project $project): bool
    {
        $membership = $this->membership($user, $project);

        return $membership !== null && $membership->canMutate();
    }

    private function membership(User $user, Project $project): ?WorkspaceMembership
    {
        return WorkspaceMembership::where('workspace_id', $project->workspace_id)
            ->where('user_id', $user->id)
            ->first();
    }
}
