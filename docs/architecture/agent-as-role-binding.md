# Agent-as-Role: Binding a Session to an Operating Role — 2026-05-17

Design spike for issue #183. Status: **recommendation made — introduce an
`OperatingRole` taxonomy and a `RoleContext`, both mirroring the workspace
binding; ViewLens becomes derived-when-bound; `agent_id` wiring stays deferred.
Four implementation issues spun off (see end).**

## Recommendation

Bind a session to an **operating role** — a new, code-level taxonomy that names
the seven functional contexts an agent can operate in. The binding mirrors the
existing `WorkspaceContext` mechanism exactly: token-bound for HTTP, env-var for
stdio, resolved through a `RoleContext` support class. Once bound, the role
selects the MCP server (its tool subset), supplies the persona via the server's
`instructions`, and drives the webapp view lens.

Do **not** bind to the `Role` model. That model is project-scoped planning data
(RACI, capacity, rates); a session binding to it would drag in a project binding
Growth does not otherwise require. The operating-role taxonomy is new.

## The three "roles" Growth already has

The issue's prose uses "role" for one concept. The codebase has three, and the
spike's first job is to separate them:

| Name | What it is | Cardinality | Where |
|---|---|---|---|
| **Role servers** | The 7 role-scoped MCP servers — `IntakeServer`, `ArchitectureServer`, `PlanningServer`, `VerificationServer`, `GovernanceServer`, `ManagementServer`, `ReadonlyServer`. Each curates a tool subset. | 7, fixed in code | `app/Mcp/Servers/` |
| **View lenses** | `ViewLens` enum (#172) — personas that filter the webapp's sidebar and dashboard panels. | 4, fixed in code (`All`, `SpecWriter`, `SpecImplementer`, `Reviewer`) | `app/Support/ViewLens.php` |
| **`Role` records** | Eloquent `Role` model — project-scoped planning artifact with responsibilities, weekly capacity, hourly rate, RACI assignments. | N, user-authored data | `app/Models/Role.php` |

The role servers and the view lenses are *almost* the same idea — a curated slice
of the surface, per working context — expressed twice, in two taxonomies that do
not line up (7 vs 4) and reference each other not at all. The `Role` model is a
different thing entirely: data about a project's process, not a description of
who is operating the tools.

**The decision:** make the role servers the canonical taxonomy. Name it
`OperatingRole` (a new enum, 7 cases, 1:1 with the servers). The view lens stops
being an independent enum and becomes a *projection* of the operating role — a
role declares which lens it shows. The `Role` model is untouched; it stays
project planning data.

This collapses two taxonomies into one and leaves the third where it belongs.

## The five questions

### 1. How does a session declare and carry its role binding?

Mirror `WorkspaceContext` — the precedent is already in the codebase and adopters
already configure it. A new `RoleContext` support class resolves the bound role
through the same first-non-null-wins chain:

1. **Token-bound** — a `role` column on `oauth_access_tokens` (HTTP MCP via
   Passport). The token *is* the session for HTTP; the role rides it the same
   way `workspace_id` already does (migration `2026_05_14_181307`).
2. **Env override** — `GROWTH_ROLE` for local stdio MCP, alongside the existing
   `GROWTH_USER_EMAIL` / `GROWTH_WORKSPACE_ID`.
3. **Unbound** — no role; the session is not operating as a role. See Q5.

Both transports, both mechanisms — exactly as `WorkspaceContext` does it. No new
binding concept is invented; an adopter who already knows how to scope a token to
a workspace scopes it to a role the same way.

One subtlety: the role binding and the MCP server are redundant for HTTP, where
the URL path (`/mcp/verification`) already selects the server. The token's `role`
is still worth carrying — it is what attribution and the lens projection read,
and it is the authority for stdio where there is no per-role URL. Where both are
present they must agree; a mismatch is a configuration error and should fail
loudly rather than silently preferring one.

### 2. How are role skills / personas authored and delivered?

**Server `instructions`, not Claude skills.** Claude skills live in
`.claude/skills/` on the operator's machine — they are a local Claude Code
construct and cannot be delivered by an MCP server to an arbitrary client. The
MCP-native channel for "behave like an experienced holder of this role" is the
server's `instructions` string, which every client receives on connect, plus the
per-tool `#[Description]` attributes.

So the persona is authored as prose on each `OperatingRole` and rendered into the
matching server's `instructions`: what the role is accountable for, what
judgement it brings, what it should *not* do, which sibling role owns the
adjacent work. This keeps the persona versioned in the repo, reviewable in a PR,
and delivered over the wire with no client-side install.

Claude skills remain available as a richer *local* augmentation for operators who
run stdio — but they are out of band, not part of the binding contract.

### 3. Does the #172 lens become derived from the role binding?

**Derived when a role is bound; independently selectable when it is not.**

The taxonomy decision above already makes the lens a projection of the operating
role. The remaining question is whether a *human's* lens selection still matters.
It does, because the human and the agent are on **different sessions**:

- The **agent** runs a role-bound MCP session. Its lens is fixed by the role —
  there is no UI for it to choose, and choosing would contradict the binding.
- The **human supervisor** runs an unbound webapp session. They are not operating
  *as* one role; they are watching an agent that is. They keep the
  self-selectable `ViewLens` so they can review across lenses — including lenses
  other than the one their agent is bound to.

So the lens is not globally "derived" or "independent" — it is derived for
role-bound sessions and selectable for unbound ones. This is the same
bound/unbound split as Q1 and Q5, applied to the read side. `ViewLens` the enum
is absorbed into `OperatingRole`; the *user preference* `users.view_lens`
survives for unbound human sessions.

### 4. Should attribution record the acting role?

**Yes — add `acting_role` to `tool_invocations` and to the audit/status-transition
events.** `RecordingCallTool` already captures `workspace_id` and `user_id` per
call; `acting_role` is read from `RoleContext` at the same point and stored
beside them. Status transitions (the verb-named transition classes) record an
audit row the same way and gain the same column.

This is the payoff of the binding: today a `tool_invocations` row says *who*
(user) and *what* (tool); with `acting_role` it says *in what capacity* — which
is exactly the "overt context that an agent is working in" the issue asks for,
made queryable.

**`agent_id` stays deferred.** The column exists on `tool_invocations` and is set
`null` today because there is no agent-principal auth path — `auth()->user()`
inside a tool is always the human `User`, established by Passport or
`AuthenticatesLocalMcpSessions`. Wiring `agent_id` means giving an `Agent` a
credential and an authentication path, which is a larger change than this spike.
The operating-role binding deliberately does **not** depend on it: a role is a
context the human's session declares, not an identity. "The human occupies the
role; the agent performs it" is satisfied by `user_id` (occupant) + `acting_role`
(capacity) without an agent principal. Treat `agent_id` as a separate, later
question — noted here so an implementation issue does not assume this spike
delivered it.

### 5. What happens when no role is bound?

`AllServer` remains the unscoped fallback — unchanged. An unbound session is a
legitimate state, not an error: it is the power-user / integration-check case
`AllServer` exists for, and it is the human supervisor's webapp session. Unbound
means: full tool surface, no persona instructions, selectable lens, `acting_role`
recorded as `null` on invocations.

The binding is **opt-in and additive**. Nothing about an existing unbound session
changes. This also keeps the rollout safe — the feature can ship before any
adopter configures a role.

## Added question — is the binding immutable for the session?

The issue's language ("a session *binds* to a role") implies the role is fixed
for the life of the session. **Confirm that: the binding is immutable.** For HTTP
it is a property of the access token; rebinding means a new token. For stdio it
is an env var fixed at process start. There is no mid-session "switch role" tool.
An agent that needs a different role starts a different session. This is stated
here so the implementation issues do not relitigate it — and because a mutable
binding would make `acting_role` on a `tool_invocations` row ambiguous as to
*when* the role was what.

## Why this shape

- **No new mechanism.** Role binding reuses the workspace-binding pattern an
  adopter already configures. One precedent, applied twice.
- **Collapses a duplicated taxonomy.** Seven role servers and four view lenses
  were the same idea expressed twice; after this they are one taxonomy with a
  read-side projection.
- **Additive and reversible.** Unbound stays the default and stays unchanged;
  the feature ships dark and lights up per token / per env var.
- **Honest about `agent_id`.** The spike explicitly scopes the agent-principal
  identity question *out*, so it is not silently assumed done.

## Implementation issues to spin off

1. **`OperatingRole` enum + `RoleContext`.** New `app/Support/OperatingRole.php`
   (7 cases, 1:1 with the role servers; each declares its server class, its
   lens, and its persona text). New `app/Support/RoleContext.php` mirroring
   `WorkspaceContext`. Migration adding `role` to `oauth_access_tokens`;
   `GROWTH_ROLE` env support in `AuthenticatesLocalMcpSessions`. Fail loudly on
   a token-role / URL-server mismatch.
2. **Persona instructions per role.** Author the seven persona strings and render
   them into each role server's `instructions`. No code mechanism beyond wiring
   `OperatingRole::personaInstructions()` into the server.
3. **Attribution.** Add `acting_role` to `tool_invocations` (read from
   `RoleContext` in `RecordingCallTool`) and to the status-transition audit
   rows. Surface it on the tool-invocations webapp feed.
4. **Lens projection.** Absorb `ViewLens` into `OperatingRole`; derive the lens
   from the bound role for role-bound sessions; keep `users.view_lens`
   selectable for unbound human sessions.

`agent_id` / agent-principal authentication is **not** in this list — it is a
separate track, noted in Q4.
