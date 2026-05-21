# Capabilities are typed Role accountabilities; Lens and served Persona derive from them

A `Role` (project-defined RACI accountability — `Engineering Lead`,
`Product Lead`, `Developer`) carries a typed set of **Capabilities**. A
Capability is a
curated, intent-named bundle of MCP tools — `manage_intent`,
`manage_requirements`, `manage_changes` — closed-set, defined in code. The
existing `Capability Surface` is reframed as a *grouping of Capabilities*
exposed as one MCP server; its `$tools` array becomes derived from the
Capabilities the surface exposes, not hand-maintained alongside.

Two projections of a Role's Capability set:

- The **Lens** — the webapp's nav-and-panel filter — is computed from the
  union of sections each Capability declares. `ViewLens` enum demoted to a
  set of preset templates a project owner can apply when defining a Role.
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
servers derive their tool list from the Capabilities they expose; the per-Role
Capability set is persisted authoritatively, not denormalised onto the User.

## Consequences

- A workspace owner/admin with **no Role** on the active project sees the union
  of all sections from all Capabilities — admins poking around a project they
  have no Role on are not blocked. This is a deliberate fallback, not the empty
  case. A non-admin project participant with no Role should not get this
  fallback merely because no one has assigned them yet.
- A user assigned to a Role with **no Capabilities** sees an empty Lens —
  no sections in the Project sidebar, no panels on the dashboard. The Lens
  is advisory: deep links to a hidden section still work.
- Every domain MCP tool is assigned to **exactly one** Capability — no orphans,
  no duplicates. Cross-cutting session or communication tools such as
  `adopt-role`, feedback, search, and queue summaries belong to an explicit
  common Capability exposed by every surface that needs them. The constraint is
  global; how it is enforced is implementation.
- A Capability declares both `tools(): array` (class strings) and
  `sections(): array` (sidebar section keys). Both are closed sets in code.
  Renaming a section is a code change, not a data migration.
- Multiple Roles per User on one project union their Capability sets — no UX
  for "which Role am I viewing as." The assign-role surface is the only knob.
- `ViewLens` as a runtime enum is gone. The four legacy cases survive only as
  named *presets* a project owner applies during Role definition; the
  preset's Capability set becomes the persisted truth, and renaming or
  retiring a preset has no migration consequence because no row references it.
- This does not soften ADR-0001. Capabilities are advisory — Growth still
  cannot gate a Client Agent. A Capability's tool list is what the *served
  Persona* enumerates, not a server-side ACL. The server-side MCP tool boundary
  remains the connected `CapabilitySurface`.
- This does not soften ADR-0002. A session adopts a project Role mid-session via
  `adopt-role`; the per-Role Persona and Capability names are returned through
  that adoption path, not through static server `instructions`.
