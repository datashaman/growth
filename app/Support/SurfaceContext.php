<?php

namespace App\Support;

use App\Models\User;
use Laravel\Passport\AccessToken;
use RuntimeException;
use Throwable;

/**
 * Single source of truth for "which capability surface is this session bound
 * to?" (#183). Modelled on {@see WorkspaceContext} — the binding rides the
 * session the same way the workspace does.
 *
 * Resolution order, first non-null wins:
 *   1. Token-bound surface (HTTP MCP: Passport access token's `surface`
 *      column).
 *   2. Environment override (local stdio MCP: GROWTH_SURFACE). Like
 *      WorkspaceContext, this step applies only once a user is bound to the
 *      session — for stdio that means after the local binding from
 *      GROWTH_USER_EMAIL/GROWTH_USER_ID has run.
 *   3. Unbound — null. The session is not operating on a surface; it gets the
 *      full surface (AllServer).
 *
 * Unlike the workspace there is no authenticated-user fallback: a surface is a
 * context a session declares, not a stored user preference.
 *
 * Override via `set()` for tests or scope-temporary work.
 */
class SurfaceContext
{
    private ?CapabilitySurface $override = null;

    private bool $overridden = false;

    public function surface(): ?CapabilitySurface
    {
        if ($this->overridden) {
            return $this->override;
        }

        return $this->fromToken() ?? $this->fromEnv();
    }

    public function requireSurface(): CapabilitySurface
    {
        $surface = $this->surface();

        if ($surface === null) {
            throw new RuntimeException('No capability surface is bound to this session.');
        }

        return $surface;
    }

    /**
     * Which resolution path produced the bound surface, or null when none did.
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
     * Fail loudly when a surface is bound to the session but the MCP server now
     * booting stands for a different surface — a configuration error (a token
     * scoped to one surface used against another surface's URL, say). A server
     * with no surface (`AllServer`) accepts any session; an unbound session is
     * fine against any server.
     *
     * @param  class-string  $serverClass
     */
    public function assertServerMatches(string $serverClass): void
    {
        $bound = $this->surface();
        $server = CapabilitySurface::forServer($serverClass);

        if ($bound === null || $server === null || $bound === $server) {
            return;
        }

        throw new RuntimeException(sprintf(
            'Session is bound to the %s surface (%s) but reached the %s server. Bind the session to the matching surface or use the %s server.',
            $bound->value,
            $this->source() ?? 'unknown',
            $server->value,
            $bound->value,
        ));
    }

    public function set(?CapabilitySurface $surface): void
    {
        $this->override = $surface;
        $this->overridden = true;
    }

    public function forget(): void
    {
        $this->override = null;
        $this->overridden = false;
    }

    private function fromToken(): ?CapabilitySurface
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

            $surface = $token instanceof AccessToken ? $token->surface : null;
        } catch (Throwable) {
            return null;
        }

        return is_string($surface) && $surface !== '' ? CapabilitySurface::tryFrom($surface) : null;
    }

    private function fromEnv(): ?CapabilitySurface
    {
        if (! auth()->check()) {
            return null;
        }

        $value = env('GROWTH_SURFACE');

        return is_string($value) && $value !== '' ? CapabilitySurface::tryFrom($value) : null;
    }
}
