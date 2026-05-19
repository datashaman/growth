# Agent Attribution: Resolving an MCP Call to an Agent — 2026-05-19

Design spike for issue #295. Status: **recommendation made — introduce an
`AgentContext` that resolves a bound agent the same way `RoleContext` resolves a
bound role; agent identity is an attribution annotation on a user-authenticated
session, not a security principal. One implementation issue spun off; #258
becomes its consumer (see end).**

## Recommendation

Carry an **agent attribution** on a session — a reliable answer to "which agent
made this call" — by reusing the binding mechanism already proven twice in the
codebase (`WorkspaceContext`, `RoleContext`): an `agent_id` column on the access
token for HTTP, a `GROWTH_AGENT_ID` env var for stdio, resolved through a new
`AgentContext` support class.

The agent is **not** a security principal. The call still authenticates as a
`User`; the agent rides alongside as a label, exactly as `workspace_id` and
`role` already do. Trust flows from the user. This is *attribution*, not
*authentication*.

## Attribution, not authentication

The #183 spike scoped "agent-principal authentication" out as a separate track.
That phrasing conflated two things this spike must keep apart:

| | Attribution | Authentication |
|---|---|---|
| What | A session annotation: which agent acts | The agent is its own principal |
| Trust | Flows from the authenticated `User` | The agent authenticates as itself |
| Cost | A token column + a resolver | `Agent` becomes `Authenticatable`; credential issuance, rotation, revocation |
| Carrier | Mirrors `WorkspaceContext` / `RoleContext` | A new auth surface |

Every known downstream consumer needs only **attribution**:

- **#258** populates the `agent_id` columns — it needs to *know* which agent, not
  to authenticate one.
- **#242** computes per-agent outcome metrics — it reads attribution.
- **#241** versions agent authority bindings — it binds *to* an agent identity;
  it does not require the agent to log in as itself.

None requires the agent to be a security principal. So this spike designs
attribution only. **Agent-as-principal (authentication) is explicitly out of
scope** — a far larger, separable effort that may never be needed, noted here so
no implementation issue assumes it was delivered.

## The current model

- The `agents` table is a bare per-project registry: `id, project_id, name,
  kind, description`, unique on `(project_id, name)`, FK to `project` with
  `cascadeOnDelete`. No credential, no session link.
- `Agent` already `morphToMany`s `Role` — the #183/#188 role work landed; an
  agent can hold roles.
- An MCP call authenticates as a `User` and, via #190, records an `acting_role`
  read from `RoleContext`. It never resolves to an `Agent`.
- Three `agent_id` columns exist and are written unconditionally `null`:
  `tool_invocations.agent_id`, `tool_feedback.agent_id`,
  `project_plan_baselines.baselined_by_agent_id`.

## The questions

### 1. How does a session declare and carry its agent?

A new `AgentContext` support class, mirroring `RoleContext` (which itself mirrors
`WorkspaceContext`), resolving through a first-non-null-wins chain:

1. **Token-bound** — an `agent_id` column on the OAuth access token (HTTP MCP via
   Passport). The agent rides the token exactly as `role` does — see migration
   `2026_05_17_121023_add_role_to_oauth_access_tokens` for the template.
2. **Env override** — `GROWTH_AGENT_ID` for local stdio MCP, alongside the
   existing `GROWTH_USER_EMAIL` / `GROWTH_USER_ID` / `GROWTH_WORKSPACE_ID` /
   `GROWTH_ROLE`.
3. **Unbound** — no agent. See Q3.

The chain is **two steps, like `RoleContext`** — no authenticated-user fallback.
There is no "user's active agent": a user is not an agent and does not always
operate through one. Inventing a `users.active_agent_id` would be wrong.

Token issuance is **not a separate effort**. `agent_id` flows onto a token the
same way `role` and `workspace_id` already do — through the OAuth grant tables
and `AccessTokenRepository`. Copying the `role` path is the whole of it.

### 2. How is the project/workspace granularity mismatch handled?

An `Agent` belongs to exactly one `project` (`agents` is unique on
`(project_id, name)`, FK to `project`). But an access token is bound to a
**workspace**, and a workspace holds many projects — each tool call carries its
own `project_id`. So a token's `agent_id` names an agent in one project while the
same token can drive calls against other projects. "Agent from project P made a
call about project Q" is possible, and incoherent.

**Decision: keep agents project-scoped; make attribution project-aware.**
`AgentContext` resolves the bound `agent_id`, and a call is agent-attributed
**only when the call's `project_id` matches the bound agent's `project_id`**. A
mismatch resolves to *unbound* (null), never an error.

A coding-agent session realistically works one repo = one project at a time, so
a project-scoped registry is defensible — probably correct. Re-scoping `agents`
to the workspace (`(workspace_id, name)`, FK to workspace) is the explicit
alternative; it is a heavy, invasive migration and is **deferred** — revisit only
if cross-project agents become a real requirement.

### 3. What happens when no agent is bound?

An unbound agent is a **legitimate terminal state, not an error** — a human, or a
user-driven session, acting directly. The three `agent_id` columns stay `null`,
which #258 already treats as the human case.

This is the key divergence from `WorkspaceContext`, which has a `requireId()`
that throws because a workspace is mandatory. **`AgentContext` must have no
`requireId()` analogue.** A null agent is a normal answer.

One guard: a token or env value naming a non-existent or foreign agent resolves
to *unbound* (null), not an error — a stale id must not break a session.

### 4. Should the Doctor tool report the bound agent?

**Yes.** `Doctor` already reports the active workspace and token bindings; it
should report the resolved agent (or "none — acting as user directly") so an
operator can see why `agent_id` is or is not being attributed. Small; folds into
the substrate implementation.

## Why this shape

- **No new mechanism.** Agent attribution is a third application of the
  workspace-binding pattern — `RoleContext` is the second, and it is the precise
  template here (two-step chain, no user fallback).
- **Additive and reversible.** Unbound stays the default; the feature ships dark
  and lights up per token / per env var. No existing session changes.
- **Honest about authentication.** The spike scopes agent-as-principal *out*, so
  it is not silently assumed done — the same discipline #183 applied to
  `agent_id`.

## Implementation issues to spin off

1. **`AgentContext` + `agent_id` token binding.** New
   `app/Support/AgentContext.php` mirroring `RoleContext`: token → env → null,
   project-aware (mismatch → unbound), **no `requireId()`**. Migration adding
   `agent_id` to the OAuth access-token / grant tables, following
   `add_role_to_oauth_access_tokens`. `GROWTH_AGENT_ID` env support alongside the
   other `GROWTH_*` stdio vars. `Doctor` reports the resolved agent. A
   non-existent / foreign / cross-project `agent_id` resolves to null.

`#258` ("Wire up agent attribution: populate the `agent_id` columns") is **not a
new issue** — it already exists and becomes the *consumer* of this substrate:
read `AgentContext` in `RecordingCallTool`, `SendFeedback`, and `BaselinePlan`.
Its blocker is re-pointed from #295 to the substrate issue above.

Agent-as-principal authentication is **not** in this list — it is a separate
track, scoped out in "Attribution, not authentication" above.
