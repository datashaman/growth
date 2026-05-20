# Capabilities are typed Role accountabilities; Lens and served Persona derive from them

A `Role` (project-defined RACI accountability — `Engineering Lead`, `Senior
Spec Writer`) carries a typed set of **Capabilities**. A Capability is a
curated, intent-named bundle of MCP tools — `manage_intent`,
`manage_requirements`, `manage_changes` — closed-set, defined in code. The
existing `Capability Surface` is reframed as a *grouping of Capabilities*
exposed as one MCP server; its `$tools` array becomes derived
(`flatMap(Capability::tools)`), not hand-maintained alongside.

Two projections of a Role's Capability set:

- The **Lens** — the webapp's nav-and-panel filter — is computed from the
  union of sections each Capability declares. `ViewLens` enum demoted to a
  set of preset templates a project owner can apply when defining a Role.
- The **served Persona text** — the advisory instruction set served to a
  Client Agent — lists the Capability names alongside the freeform persona
  prose stored on the Role.

We chose this over the prior shape (a self-selected `User::$view_lens` enum
decoupled from Role assignments) because that shape made the webapp's
section visibility *orthogonal* to the project's RACI structure — a Spec
Writer-assigned user could still self-select the Reviewer lens and miss the
parts of the dashboard their Role is accountable for. The Capability concept
unifies "what tools an agent can fire" (MCP side) and "what sections a user
sees" (webapp side) under one Role-owned source of truth.

The cutover lands in one PR — no transition shims. `User::$view_lens` is
dropped; the `lens-switcher` Livewire component is deleted; `CapabilitySurface`
servers derive their tool list from the Capabilities they expose; a
`role_capabilities` pivot persists the per-Role assignment.

## Consequences

- A Role with **no Capabilities** sees an **empty webapp Lens** — no sections,
  only top-level nav. The deliberate default for unassigned users is to fall
  back to *all* Capabilities (visible everywhere), so observers and admins
  poking around a project they have no Role on are not blocked. The Lens is
  advisory: deep links to a hidden section still work.
- Adding a new MCP tool requires assigning it to **exactly one** Capability.
  An arch test enforces this; an orphan tool fails CI.
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
  Persona* enumerates, not a server-side ACL.
