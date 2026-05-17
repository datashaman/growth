<?php

namespace App\Support;

use App\Models\User;
use App\Models\Workspace;
use Laravel\Passport\AccessToken;
use RuntimeException;
use Throwable;

/**
 * Single source of truth for "which workspace is active right now?".
 *
 * Resolution order, first non-null wins:
 *   1. Token-bound workspace (HTTP MCP: Passport access token's workspace_id column).
 *   2. Environment override (local stdio MCP: GROWTH_WORKSPACE_ID).
 *   3. Authenticated user's active_workspace_id (web/Livewire).
 *
 * The auth-check fallback (env or user) only applies once a user is bound to the
 * request; for the env path that means after the local stdio binding has run.
 *
 * Override via `set()` for tests or scope-temporary work.
 */
class WorkspaceContext
{
    private ?string $override = null;

    private bool $overridden = false;

    public function id(): ?string
    {
        if ($this->overridden) {
            return $this->override;
        }

        $tokenWorkspaceId = $this->fromToken();
        if ($tokenWorkspaceId !== null) {
            return $tokenWorkspaceId;
        }

        $envWorkspaceId = $this->fromEnv();
        if ($envWorkspaceId !== null) {
            return $envWorkspaceId;
        }

        return $this->fromAuthenticatedUser();
    }

    public function requireId(): string
    {
        $id = $this->id();

        if ($id === null) {
            throw new RuntimeException('No active workspace is bound to this request.');
        }

        return $id;
    }

    /**
     * Which resolution path produced the active workspace, or null when none did.
     *
     * @return 'override'|'token'|'env'|'user'|null
     */
    public function source(): ?string
    {
        if ($this->overridden) {
            return $this->override === null ? null : 'override';
        }

        if ($this->fromToken() !== null) {
            return 'token';
        }

        if ($this->fromEnv() !== null) {
            return 'env';
        }

        if ($this->fromAuthenticatedUser() !== null) {
            return 'user';
        }

        return null;
    }

    public function workspace(): ?Workspace
    {
        $id = $this->id();

        return $id === null ? null : Workspace::find($id);
    }

    public function set(?string $workspaceId): void
    {
        $this->override = $workspaceId;
        $this->overridden = true;
    }

    public function forget(): void
    {
        $this->override = null;
        $this->overridden = false;
    }

    private function fromToken(): ?string
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return null;
        }

        if (! method_exists($user, 'token')) {
            return null;
        }

        try {
            $token = $user->token();

            $workspaceId = $token instanceof AccessToken ? $token->workspace_id : null;
        } catch (Throwable) {
            return null;
        }

        return is_string($workspaceId) && $workspaceId !== '' ? $workspaceId : null;
    }

    private function fromEnv(): ?string
    {
        if (! auth()->check()) {
            return null;
        }

        $value = env('GROWTH_WORKSPACE_ID');

        return is_string($value) && $value !== '' ? $value : null;
    }

    private function fromAuthenticatedUser(): ?string
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return null;
        }

        $workspaceId = $user->active_workspace_id;

        return is_string($workspaceId) && $workspaceId !== '' ? $workspaceId : null;
    }
}
