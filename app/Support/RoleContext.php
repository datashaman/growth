<?php

namespace App\Support;

use App\Models\User;
use Laravel\Passport\AccessToken;
use RuntimeException;
use Throwable;

/**
 * Single source of truth for "which operating role is this session bound to?"
 * (#183). Modelled on {@see WorkspaceContext} — the binding rides the session
 * the same way the workspace does.
 *
 * Resolution order, first non-null wins:
 *   1. Token-bound role (HTTP MCP: Passport access token's `role` column).
 *   2. Environment override (local stdio MCP: GROWTH_ROLE).
 *   3. Unbound — null. The session is not operating as a role; it gets the
 *      full surface (AllServer) and a self-selected ViewLens.
 *
 * Unlike the workspace there is no authenticated-user fallback: a role is a
 * context a session declares, not a stored user preference.
 *
 * Override via `set()` for tests or scope-temporary work.
 */
class RoleContext
{
    private ?OperatingRole $override = null;

    private bool $overridden = false;

    public function role(): ?OperatingRole
    {
        if ($this->overridden) {
            return $this->override;
        }

        return $this->fromToken() ?? $this->fromEnv();
    }

    public function requireRole(): OperatingRole
    {
        $role = $this->role();

        if ($role === null) {
            throw new RuntimeException('No operating role is bound to this session.');
        }

        return $role;
    }

    /**
     * Which resolution path produced the bound role, or null when none did.
     *
     * @return 'override'|'token'|'env'|null
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

        return null;
    }

    /**
     * Fail loudly when a role is bound to the session but the MCP server now
     * booting stands for a different role — a configuration error (a token
     * scoped to one role used against another role's URL, say). A server with
     * no role (`AllServer`) accepts any session; an unbound session is fine
     * against any server.
     *
     * @param  class-string  $serverClass
     */
    public function assertServerMatches(string $serverClass): void
    {
        $bound = $this->role();
        $server = OperatingRole::forServer($serverClass);

        if ($bound === null || $server === null || $bound === $server) {
            return;
        }

        throw new RuntimeException(sprintf(
            'Session is bound to the %s role (%s) but reached the %s server. Bind the session to the matching role or use the %s server.',
            $bound->value,
            $this->source() ?? 'unknown',
            $server->value,
            $bound->value,
        ));
    }

    public function set(?OperatingRole $role): void
    {
        $this->override = $role;
        $this->overridden = true;
    }

    public function forget(): void
    {
        $this->override = null;
        $this->overridden = false;
    }

    private function fromToken(): ?OperatingRole
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

            $role = $token instanceof AccessToken ? $token->role : null;
        } catch (Throwable) {
            return null;
        }

        return is_string($role) && $role !== '' ? OperatingRole::tryFrom($role) : null;
    }

    private function fromEnv(): ?OperatingRole
    {
        if (! auth()->check()) {
            return null;
        }

        $value = env('GROWTH_ROLE');

        return is_string($value) && $value !== '' ? OperatingRole::tryFrom($value) : null;
    }
}
