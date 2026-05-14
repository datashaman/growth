# apply-manifest Design — 2026-05-14

Design doc for issue #34. Status: **draft, awaiting decisions on the open questions below.**

## Goal

One MCP call applies a YAML/JSON manifest describing a Growth project's full
structure — capabilities, concerns, views, work items, milestones, roles,
baselines, links — and `export-manifest` emits the inverse. The manifest
becomes a `project.growth.yaml` (or `.json`) committed alongside the code
it describes, turning Growth from "MCP-only" into "GitOps-compatible."

Today a small project bootstraps via ~20+ individual `upsert-*` calls. The
manifest collapses that to one call and lets project structure live in
version control.

## Non-goals

- Re-implementing every single MCP write tool inside the manifest schema.
  The manifest covers the **structural** entities (project, capabilities,
  architecture, plan); it does **not** cover transactional events (test
  runs, verification runs, review decision events, check-run evidence,
  change-approval events). Those stay as live MCP writes.
- Migrating between projects, copying templates, or templating
  substitutions. Templates are issue #40, on top of this.
- Diff-only mode (computing changes without applying). That's a likely
  follow-up but not v1.

## Manifest shape

Single root document:

```yaml
project:
  name: "TodoMVC"
  description: "Standalone todo app."
  rigor_level: 2
  status: active

stakeholders:
  - slug: product-owner
    name: "Product Owner"
    role: sponsor

concerns:
  - slug: persistence
    text: "Todos persist across reloads."
    stakeholders: [product-owner]

capabilities:
  - slug: add-todo
    layer: software
    type: functional
    text: "The app shall add a todo when the user submits non-empty text."
    priority: high
    acceptance_checks:
      - "Submitting non-empty text creates one active todo."

architecture:
  viewpoints:
    - slug: logical
      name: "Logical"
      concerns: [persistence]
  views:
    - slug: top-level
      viewpoint: logical
      name: "Top-level"
      concerns: [persistence]
      elements:
        - slug: store
          kind: component
          name: "TodoStore"

plan:
  scope_summary: "Single-page todo app."
  approach: "Local-first with optional sync."
  roles:
    - slug: frontend
      name: "Frontend"
  milestones:
    - slug: m1-mvp
      name: "MVP"
      target_date: 2026-06-01
  work_items:
    - slug: wi-1
      name: "Implement add-todo"
      kind: feature
      capabilities: [add-todo]
      milestone: m1-mvp
      responsible_role: frontend

verification:
  plans:
    - slug: unit
      level: unit
      name: "Unit"
      cases:
        - slug: c-add
          name: "add-todo creates a row"
          capabilities: [add-todo]
```

Every entity has a **slug** local to the manifest. Cross-references
(`stakeholders: [product-owner]`, `capabilities: [add-todo]`) resolve to
slugs first, then to ULIDs once the entity is upserted.

## Identity model

- **Slug → ULID resolution** happens in a single pass before any writes.
  Unknown slugs fail validation.
- **Existing project**: if `project.id` is set on the manifest, that
  project is the upsert target. Slugs of existing entities are matched
  by a uniqueness key per entity type (most use `(project_id, name)`;
  capabilities use `(project_id, slug)` if a slug column is added — see
  open question 3 below).
- **Anonymous project** (no id): a new project is created and every
  entity is created fresh.

## Conflict modes

Single `mode` parameter on the tool, three values:

| mode      | Existing entity behavior |
|-----------|--------------------------|
| `fail`    | Any name collision fails the whole apply (transaction rolls back). Safest. Default. |
| `merge`   | Existing entities are updated in place; new entities created. Idempotent re-apply. |
| `replace` | Existing entities are deleted and re-created from the manifest. Destructive; requires `confirm: <project-name>` token. |

`merge` is the GitOps mode. Re-applying an unchanged manifest is a no-op.

## Dry-run

`apply-manifest --dry-run` returns the same response shape as a real
apply (counts of created/updated/deleted entities, plus a list of slug →
ULID resolutions) but performs no writes. Implemented inside a single
transaction that always rolls back.

## Ordering

Topological per entity type:

```
project
  → stakeholders, concerns
  → capabilities (needs concerns)
  → architecture viewpoints
  → architecture views (needs viewpoints + concerns)
  → architecture elements (needs views)
  → plan
  → roles, milestones (needs plan)
  → work items (needs capabilities, milestones, roles)
  → verification plans
  → verification cases (needs capabilities, plans)
```

Same-type cycles (e.g. work-item dependencies) resolved by a second
pass after all rows of that type exist.

## Idempotency

`merge` mode + stable slugs means the same manifest applied twice
produces no DB changes on the second apply. The response counts make
that visible.

## export-manifest

Inverse of apply: project ULID in, manifest YAML out. Slugs are
auto-generated from names (`kebab-case`); collisions get a numeric
suffix. Output is byte-identical across runs (deterministic ordering)
so it's diff-friendly in git.

## File location convention

`project.growth.yaml` at the repo root, or whatever path the caller
passes to `apply-manifest`. The tool doesn't read the file — that's the
client's job. The MCP tool just takes the parsed JSON/YAML as input.

## Open questions

1. **YAML or JSON in MCP?** MCP transports JSON natively. We could
   either (a) accept JSON only and leave YAML parsing to the client,
   or (b) accept either, parsing YAML server-side. Recommendation: (a)
   — keeps the tool boundary clean, lets clients pick a parser.

2. **Transaction granularity.** All-or-nothing per `apply-manifest`
   call is the safe default but means a partial-success scenario (one
   capability has a typo, the rest are fine) rejects everything.
   Alternative: per-entity-type granularity with a `partial: true`
   response field. Recommendation: all-or-nothing for v1; revisit.

3. **Capability slugs in the DB.** Capabilities (`Requirement` model)
   currently have no `slug` column — matching is by `(project_id,
   text)` today, which is fragile. Should we add a `slug` column for
   manifest re-apply? Adds a migration. Recommendation: yes, in this
   slice; otherwise `merge` mode can't reliably match.

4. **Out-of-band changes.** If a project's structure was edited via
   MCP between `export-manifest` and `apply-manifest`, the next apply
   in `merge` mode will silently overwrite those edits. Should
   `merge` warn about updated_at drift? Recommendation: report which
   entities changed, but proceed.

5. **Tool exposure.** `apply-manifest` is a powerful, project-wide
   mutation. Should it live on the `intake` server only, or also on
   `planning` and `architecture`? Recommendation: `intake` only, plus
   `AllServer` via auto-discovery. Other roles can call it through
   the `all` server.

## Slicing plan

1. **Slice A** — schema + dry-run on a minimal subset
   (project + capabilities + concerns + stakeholders). Validates the
   slug → ULID resolution and conflict-mode plumbing.
2. **Slice B** — architecture (viewpoints, views, elements).
3. **Slice C** — plan (roles, milestones, work items + links).
4. **Slice D** — verification (plans, cases).
5. **Slice E** — `export-manifest` round trip, idempotency proof.
6. **Slice F** — capability slug migration (open question 3).
7. **Slice G** — starter templates (issue #40).

Each slice is a separable PR.
