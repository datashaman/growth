# apply-manifest Design — 2026-05-14

Design doc for issue #34. Status: **decisions made on every open question (see below); ready to start slice A.**

## Goal

One MCP call applies a YAML/JSON manifest describing a Growth project's full
structure — requirements, concerns, views, work items, milestones, roles,
baselines, links — and `export-manifest` emits the inverse. The manifest
becomes a `project.growth.yaml` (or `.json`) committed alongside the code
it describes, turning Growth from "MCP-only" into "GitOps-compatible."

Today a small project bootstraps via ~20+ individual `upsert-*` calls. The
manifest collapses that to one call and lets project structure live in
version control.

## Non-goals

- Re-implementing every single MCP write tool inside the manifest schema.
  The manifest covers the **structural** entities (project, requirements,
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

requirements:
  - slug: add-todo
    layer: software
    type: functional
    text: "The app shall add a todo when the user submits non-empty text."
    priority: high
    acceptance_checks:
      - "Submitting non-empty text creates one active todo."

architecture:
  viewpoints:
    # Custom viewpoints only. Built-in viewpoint names (context, logical,
    # composition, …) are reserved and referenced directly by views.
    - slug: custom-logical
      name: "Custom Logical"
      concerns: ["scalability"]       # free-text categories stored on the viewpoint
      element_types: ["component", "connector"]
      languages: ["mermaid"]
  views:
    - slug: top-level
      viewpoint: custom-logical       # slug or built-in viewpoint name
      name: "Top-level"
      addresses_concerns: [persistence]  # refs to project concerns (slug or text)
      elements:
        - slug: store
          kind: entity                # entity | relationship | attribute | constraint
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
      requirements: [add-todo]
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
          requirements: [add-todo]
```

Every entity has a **slug** local to the manifest. Cross-references
(`stakeholders: [product-owner]`, `requirements: [add-todo]`) resolve to
slugs first, then to ULIDs once the entity is upserted.

## Identity model

- **Slug → ULID resolution** happens in a single pass before any writes.
  Unknown slugs fail validation.
- **Existing project**: if `project.id` is set on the manifest, that
  project is the upsert target. Slugs of existing entities are matched
  by a uniqueness key per entity type (most use `(project_id, name)`;
  requirements use `(project_id, slug)` if a slug column is added — see
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
  → requirements (needs concerns)
  → architecture viewpoints
  → architecture views (needs viewpoints + concerns)
  → architecture elements (needs views)
  → plan
  → roles, milestones (needs plan)
  → work items (needs requirements, milestones, roles)
  → verification plans
  → verification cases (needs requirements, plans)
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

## Decisions

1. **JSON-only at the MCP boundary.** The repo file stays
   `project.growth.yaml` for humans, but the MCP tool accepts a JSON
   `manifest` argument. Clients parse YAML themselves before calling.
   Rationale: MCP transports JSON natively; no server-side YAML
   dependency; clients pick their own parser.

2. **All-or-nothing transactions.** One `apply-manifest` call = one
   DB transaction. Any validation or write error rolls back the whole
   apply. Partial-success would make recovery harder than the typo it
   masks.

3. **Add a `slug` column to requirements.** The `Requirement` model
   gains a `slug` string column, unique per project. Folded into
   **slice A** as part of the schema work — `merge` mode can't
   reliably match without it. Existing rows backfill a slug derived
   from `text` on the migration.

4. **Report drift, proceed.** Manifest entries carry an optional
   `_exported_at` (timestamp from the last `export-manifest`). On
   apply, the tool compares each entity's current `updated_at` to
   the manifest's `_exported_at` and includes a `drift` array in the
   response listing slugs whose `updated_at > _exported_at`. The
   apply still proceeds — source of truth is the manifest. Callers
   that want stricter behavior can read the `drift` array and react
   client-side.

5. **New `ManagementServer` role server.** The current servers
   (`intake`, `planning`, `architecture`, `verification`,
   `governance`, `readonly`) all operate _within_ a project. Project
   lifecycle (create, update, archive, delete, manifest apply/export)
   has no clear home — `Projects/CreateProject` and
   `Projects/UpdateProject` are currently orphans only reachable
   through `AllServer` auto-discovery. The new `ManagementServer`
   registers: `create-project`, `update-project`, `upsert-project`,
   `delete-project`, `apply-manifest`, `export-manifest`. Adds a
   `/mcp/management` route and a `Mcp::local('management', …)`
   binding. `AllServer` continues to auto-discover everything.

## Slicing plan

1. **Slice A** — `ManagementServer` + requirement slug migration + the
   minimal `apply-manifest` (project + stakeholders + concerns +
   requirements) with `fail`/`merge`/`replace` modes and dry-run.
   Establishes the slug → ULID resolver, transaction wrapper, and the
   drift report.
2. **Slice B** — architecture (viewpoints, views, elements).
3. **Slice C** — plan (roles, milestones, work items + links).
4. **Slice D** — verification (plans, cases).
5. **Slice E** — `export-manifest` round trip, idempotency proof.
6. **Slice F** — starter templates (issue #40).

Each slice is a separable PR. Slice A is the riskiest because it
proves the architecture works; B–D are mechanical extensions.
