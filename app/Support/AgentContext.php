<?php

namespace App\Support;

use App\Models\Agent;
use App\Models\User;
use Laravel\Passport\AccessToken;
use Throwable;

/**
 * Single source of truth for "which agent is acting in this session?" (#295).
 * Modelled on {@see RoleContext} / {@see WorkspaceContext} — the binding rides
 * the session the same way the workspace and operating role do.
 *
 * Resolution order, first non-null wins:
 *   1. Token-bound agent (HTTP MCP: Passport access token's `agent_id` column).
 *   2. Environment override (local stdio MCP: GROWTH_AGENT_ID), once a user is
 *      bound to the session.
 *   3. Unbound — null. The session is a user acting directly, not through an
 *      agent; attribution columns stay null.
 *
 * The agent is an attribution annotation, never a security principal — the
 * session still authenticates as a User. Unlike WorkspaceContext there is
 * deliberately no `requireId()`: an unbound agent is a valid, normal state.
 *
 * A token/env `agent_id` naming a non-existent, foreign-workspace, or
 * cross-project agent resolves to unbound (null) rather than raising — a stale
 * id must not break a session. (The workspace check is free: {@see Agent}'s
 * owner scope already filters to agents in the active workspace.)
 *
 * Override via `set()` for tests or scope-temporary work.
 */
class AgentContext
{
    private ?Agent $override = null;

    private bool $overridden = false;

    /**
     * The agent bound to this session, or null when none is bound or the
     * bound id is stale / foreign.
     */
    public function agent(): ?Agent
    {
        if ($this->overridden) {
            return $this->override;
        }

        $id = $this->fromToken() ?? $this->fromEnv();

        // A missing, stale, or foreign-workspace id simply finds nothing —
        // Agent::find returns null, and the owner scope already excludes
        // agents outside the active workspace. A genuine DB failure is left
        // to surface rather than being masked as an unbound session.
        return $id === null ? null : Agent::find($id);
    }

    /**
     * The bound agent's id, or null when the session has no agent.
     */
    public function id(): ?string
    {
        return $this->agent()?->getKey();
    }

    /**
     * The bound agent's id, but only when that agent belongs to the given
     * project — attribution is project-aware. An agent is project-scoped
     * while a session is workspace-scoped, so a call against a different
     * project in the same workspace must not be attributed to it. A mismatch,
     * or a null project, resolves to null — never an error.
     */
    public function idForProject(?string $projectId): ?string
    {
        if ($projectId === null) {
            return null;
        }

        $agent = $this->agent();

        return $agent !== null && $agent->project_id === $projectId
            ? $agent->getKey()
            : null;
    }

    /**
     * Which resolution path produced the bound agent, or null when none did.
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

    public function set(?Agent $agent): void
    {
        $this->override = $agent;
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

        if (! $user instanceof User || ! method_exists($user, 'token')) {
            return null;
        }

        try {
            $token = $user->token();

            $agentId = $token instanceof AccessToken ? $token->agent_id : null;
        } catch (Throwable) {
            return null;
        }

        return is_string($agentId) && $agentId !== '' ? $agentId : null;
    }

    private function fromEnv(): ?string
    {
        if (! auth()->check()) {
            return null;
        }

        $value = env('GROWTH_AGENT_ID');

        return is_string($value) && $value !== '' ? $value : null;
    }
}
