<?php

namespace App\Support;

use App\Models\McpSession;
use App\Models\Role;
use Laravel\Mcp\Request;
use RuntimeException;

/**
 * Single source of truth for "which project Role has this session adopted?"
 * (#314). Unlike {@see SurfaceContext}, the binding is not carried on the
 * access token or an env var — a Role is adopted *mid-session* via the
 * `adopt-role` tool, so it lives in the server-side {@see McpSession} store
 * (ADR-0002), keyed by the transport session id and the authenticated user.
 *
 * A Role and a Capability Surface are orthogonal: there is deliberately no
 * `assertServerMatches()` here. The adopted Role is persona and attribution
 * only — it does not scope which tools a session may call.
 *
 * Override via `set()` for tests or scope-temporary work.
 */
class RoleContext
{
    private ?Role $override = null;

    private bool $overridden = false;

    /**
     * The project Role bound to the current MCP session, or null when the
     * session has not adopted one.
     */
    public function role(): ?Role
    {
        if ($this->overridden) {
            return $this->override;
        }

        return $this->mcpSession()?->role;
    }

    /**
     * Pin a project Role to the current MCP session, writing the binding to
     * the server-side session store. The row is created lazily on the first
     * adoption and updated in place thereafter.
     */
    public function adopt(Role $role): McpSession
    {
        $sessionId = $this->currentMcpSessionId();
        $userId = auth()->id();

        if ($sessionId === null || $userId === null) {
            throw new RuntimeException('Cannot adopt a role without an authenticated MCP session.');
        }

        return McpSession::updateOrCreate(
            ['mcp_session_id' => $sessionId, 'user_id' => $userId],
            ['role_id' => $role->getKey()],
        );
    }

    public function set(?Role $role): void
    {
        $this->override = $role;
        $this->overridden = true;
    }

    public function forget(): void
    {
        $this->override = null;
        $this->overridden = false;
    }

    /**
     * The session store row for the current (session id, user) pair, or null
     * when the session is anonymous, unidentified, or has adopted no Role.
     */
    private function mcpSession(): ?McpSession
    {
        $sessionId = $this->currentMcpSessionId();
        $userId = auth()->id();

        if ($sessionId === null || $userId === null) {
            return null;
        }

        return McpSession::where('mcp_session_id', $sessionId)
            ->where('user_id', $userId)
            ->first();
    }

    /**
     * The transport session id of the in-flight MCP request, or null outside
     * an MCP request (the webapp) or when the transport assigned none.
     */
    private function currentMcpSessionId(): ?string
    {
        if (! app()->bound('mcp.request')) {
            return null;
        }

        $request = app('mcp.request');
        $sessionId = $request instanceof Request ? $request->sessionId() : null;

        return is_string($sessionId) && $sessionId !== '' ? $sessionId : null;
    }
}
