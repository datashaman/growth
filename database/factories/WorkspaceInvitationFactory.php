<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;
use App\Models\WorkspaceMembership;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkspaceInvitation>
 */
class WorkspaceInvitationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workspace_id' => fn () => User::factory()->create()->active_workspace_id,
            'email' => fn () => $this->faker->unique()->safeEmail(),
            'role' => WorkspaceMembership::ROLE_MEMBER,
            'token' => fn () => WorkspaceInvitation::generateToken(),
            'invited_by_user_id' => null,
            'expires_at' => fn () => WorkspaceInvitation::defaultExpiry(),
            'accepted_at' => null,
        ];
    }

    public function expired(): static
    {
        return $this->state(fn () => ['expires_at' => now()->subDay()]);
    }

    public function accepted(): static
    {
        return $this->state(fn () => ['accepted_at' => now()]);
    }

    public function forWorkspace(Workspace $workspace): static
    {
        return $this->state(fn () => ['workspace_id' => $workspace->id]);
    }
}
