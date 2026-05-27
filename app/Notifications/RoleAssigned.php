<?php

namespace App\Notifications;

use App\Models\Role;

/**
 * Catalogue event `role.assigned`.
 *
 * Payload: the role the recipient was assigned to.
 * Recipients: the assigned user (a personal event — sent only to them).
 * Emitted by the AssignRoles tool when a user is newly attached to a role.
 */
class RoleAssigned extends WorkspaceNotification
{
    public function __construct(private readonly Role $role) {}

    public function event(): string
    {
        return 'role.assigned';
    }

    public function title(): string
    {
        return 'Role assigned to you';
    }

    public function body(): string
    {
        return sprintf('You were assigned the role “%s”.', $this->role->name);
    }

    public function url(): ?string
    {
        return route('roles', ['project' => $this->role->project_id], false);
    }

    public function subject(): array
    {
        return ['role', $this->role->id];
    }
}
