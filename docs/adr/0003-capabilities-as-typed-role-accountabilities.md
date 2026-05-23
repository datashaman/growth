# Capabilities are typed Role accountabilities; Lens and served Persona derive from them

A `Role` (project-defined RACI accountability — `Engineering Lead`,
`Product Lead`, `Developer`) carries a typed set of **Capabilities**. A
Capability is a curated, intent-named accountability — `manage_intent`,
`manage_requirements`, `manage_changes` — closed-set, defined in code. The
existing `Capability Surface` remains the MCP server/tool grouping a session
connects to; Capabilities do not currently derive or replace server `$tools`
lists.

Two projections of a Role's Capability set:

- The **Lens** — the webapp's nav-and-panel filter — is computed from the
  union of sections each Capability declares. The runtime `ViewLens` enum is
  gone; project owners assign concrete Capabilities directly to Roles.
- The **served Persona text** — the advisory instruction set served to a
  Client Agent when a session adopts a Role — lists the Capability names
  alongside the freeform persona prose stored on the Role.

We chose this over the prior shape (a self-selected `User::$view_lens` enum
decoupled from Role assignments) because that shape made the webapp's
section visibility *orthogonal* to the project's RACI structure — a Spec
Writer-assigned user could still self-select the Reviewer lens and miss the
parts of the dashboard their Role is accountable for. The Capability concept
unifies "what accountabilities this Role is expected to exercise" and "what
sections a user sees" under one Role-owned source of truth.

This does **not** make the Role a server-side MCP authorisation boundary. The
session still connects to one `CapabilitySurface`, and that surface remains the
actual MCP tool list advertised by the server. A Role's Capability set is
advisory context and UI projection data: it shapes the Lens, is served back in
Role persona text, and is recorded for attribution, but it does not prevent a
Client Agent from calling a tool that the connected surface exposes.

The cutover lands in one PR — no transition shims. `User::$view_lens` is
dropped; the `lens-switcher` Livewire component is deleted; `CapabilitySurface`
servers keep owning their advertised tool lists; the per-Role Capability set is
persisted authoritatively, not denormalised onto the User.

## Consequences

- A workspace owner/admin with **no Role** on the active project sees the union
  of all sections from all Capabilities — admins poking around a project they
  have no Role on are not blocked. This is a deliberate fallback, not the empty
  case. A non-admin project participant with no Role should not get this
  fallback merely because no one has assigned them yet.
- A non-mutator user assigned to a Role with **no Capabilities** sees an empty
  Lens — no sections in the Project sidebar, no panels on the dashboard. The
  Lens is advisory: deep links to a hidden section still work. Workspace
  owners/admins keep the see-all fallback when they have no effective
  Capabilities so they are not trapped by legacy or incomplete Role setup.
- A Capability declares webapp `sections()` and dashboard `panels()`. It does
  not declare MCP tool class lists in the shipped model. Deriving MCP server
  tool lists from Capabilities is deferred until the read/write shape for
  surfaces such as `ReadonlyServer` is decided.
- Multiple Roles per User on one project union their Capability sets — no UX
  for "which Role am I viewing as." The assign-role surface is the only knob.
- `ViewLens` as a runtime enum is gone. Legacy lens names are not persisted
  runtime state; if presets are introduced later, applying one must copy
  concrete Capability slugs onto the Role.
- This does not soften ADR-0001. Capabilities are advisory — Growth still
  cannot gate a Client Agent. The server-side MCP tool boundary remains the
  connected `CapabilitySurface`.
- This does not soften ADR-0002. A session adopts a project Role mid-session via
  `adopt-role`; the per-Role Persona and Capability names are returned through
  that adoption path, not through static server `instructions`.
