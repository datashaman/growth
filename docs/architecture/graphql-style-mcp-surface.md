# GraphQL-Style MCP Surface Investigation -- 2026-05-23

Issue: [#458](https://github.com/datashaman/growth/issues/458)

Status: **recommendation made -- do not replace explicit MCP tools with a broad
GraphQL-style mutation surface. Prefer explicit workflow tools plus better
discovery. If consolidation pressure remains, prototype a small read-only
project projection query alongside the current tools.**

## Context

Growth exposes a broad MCP surface through capability-surface servers:
`management`, `intake`, `architecture`, `planning`, `verification`,
`governance`, `readonly`, and the deduplicated `all` union. Each tool carries a
name, description, input schema, optional output schema, and safety annotations
such as `IsReadOnly` or `IsDestructive`.

The explicit-tool model has real costs:

- Clients may need to inspect many tools before choosing the right operation.
- Entity CRUD families can feel repetitive.
- Cross-cutting read workflows can fan out across several list/detail tools.

It also carries important semantics:

- Tool names are workflow language, not only data access language.
- Destructive actions can require explicit confirmation arguments.
- Status transitions are separated from content upserts.
- MCP annotations and descriptions are visible at discovery time.
- Every invocation is recorded with tool name, arguments shape, return shape,
  success/failure, transport, workspace, user, agent, surface, and adopted role.

The question is whether GraphQL, or a GraphQL-like query/mutation layer, would
reduce surface sprawl without weakening those semantics.

## Comparison

| Concern | Explicit MCP tools | GraphQL-style consolidated surface |
|---|---|---|
| Discovery | Many tools, but each tool advertises intent, schema, and safety metadata directly. Capability surfaces keep lists bounded. | One or a few tools are easier to find, but the client must inspect and reason over a second schema inside the tool. |
| Agent planning | Calls are reviewable by name: `complete-work-item`, `approve-change-request`, `delete-project`. | Calls become opaque unless the query/mutation text is parsed and reviewed. The outer tool name may just be `query` or `mutate`. |
| Workflow language | Tool names encode domain transitions and guardrails. | CRUD-like mutations tend to flatten workflow distinctions unless the schema re-creates them as explicit mutation fields. |
| Validation | Laravel request validation stays close to each operation. Foreseeable failures return user-facing `Response::error(...)`. | Validation must be routed through a generic dispatcher while preserving field-level and operation-level errors. |
| Workspace scoping | Each tool can scope model lookup through `WorkspaceContext` and owner-specific rules. | Every resolver must enforce the same scoping rules. The generic layer is not itself a security boundary. |
| Audit | `tool_invocations.tool_name` names the domain action, and argument/return shapes are useful summaries. | A generic `graphql-query` or `graphql-mutate` tool would need to record operation name, selected fields, resolver names, and per-mutation outcomes to avoid losing audit value. |
| MCP annotations | Read-only/destructive annotations attach cleanly to a whole tool. | A single mutation endpoint can contain safe, destructive, reversible, and irreversible operations; annotations no longer describe the actual requested operation. |
| Backward compatibility | Existing clients continue to work as tools are added. | A new layer can be additive, but replacing tools would break clients and documentation. |

## Operations that should remain explicit

The following categories should stay as named MCP tools:

- Lifecycle transitions: project activate/archive/close/restore, work-item
  start/complete/block/unblock/cancel/reopen, plan baseline/activate/close,
  risk transitions, review transitions, anomaly transitions, release and
  deployment transitions.
- Decision and approval actions: change-request approve/reject/defer,
  decision-request answer/cancel, finding disposition/resolve/accept/close.
- Destructive operations: project, requirement, work item, mockup, source,
  review, risk, deployment, release, and other delete/cleanup tools.
- Workflow generators and briefs: plan slicing, implementation briefs, review
  briefs, mockup design briefs, GitHub sync scaffolding, manifest apply/export.
- Tools whose description is itself agent guidance, such as upsert tools that
  explain status is not set there and name the transition tools to use instead.

These operations are not merely CRUD. Their names, schemas, confirmations,
descriptions, and audit entries are part of Growth's product surface.

## Operations that could consolidate

A consolidated layer is most plausible for read-only projection, especially
where the client is composing a project context bundle:

- Project overview fields, requirements, work items, milestones, risks, reviews,
  verification plans/cases/runs, architecture views/elements, and trace edges.
- Filtering and pagination over list-style reads.
- Cross-artifact joins where the current alternative is several list calls plus
  local matching.

Growth already has partial answers here:

- MCP resources expose canonical project documents and manifest sections.
- MCP apps render dashboard, gate status, requirement explorer, and trace graph
  views.
- `search` gives a broad workspace-level lookup entrypoint.
- `export-manifest` and manifest section resources provide bounded project data
  slices.

That means a GraphQL-style read experiment should prove it adds something beyond
resources, apps, search, and manifests before becoming a permanent public
surface.

## Required design rules for any prototype

If Growth prototypes a consolidated read/query tool, it should follow these
rules:

- Read-only first. Do not include mutations in the first slice.
- Keep explicit MCP tools as the canonical write and transition surface.
- Require a project id or reference and scope all data through the active
  workspace.
- Bound response size with pagination, depth limits, selected section limits, or
  named projections.
- Return structured JSON with a declared output schema where practical.
- Record the requested projection name, selected sections, filters, and result
  shape so audit history stays meaningful.
- Avoid making GraphQL the authorization boundary. It is only a transport and
  projection language; model and workspace rules remain in the resolvers.
- Prefer named projections before arbitrary free-form query text if that keeps
  calls easier to review.

## Candidate prototype

If consolidation pressure remains after improving discovery, build one
experimental read-only tool:

`query-project-context`

Inputs:

- `project`: project id, slug, or repository reference.
- `sections`: bounded list such as `["requirements", "work_items", "risks",
  "reviews", "verification", "architecture", "trace"]`.
- `filters`: optional per-section filters, limited to fields already supported
  by existing list tools.
- `include_links`: boolean for trace/resource links.
- `limit`: per-section item limit.

The tool should return a structured project context bundle and links to the
canonical resources for deeper reads. It should live on `readonly` and `all`
only. It should not accept writes, transitions, deletes, or arbitrary resolver
execution.

This is GraphQL-like rather than necessarily GraphQL: the valuable property is
selective, bounded projection across related project artifacts, not adopting a
full GraphQL server.

## Recommendation

Do not pursue a broad GraphQL-style mutation layer for Growth now.

The current explicit MCP tool model is verbose, but it preserves safety,
workflow language, client-visible annotations, per-operation validation, and
useful audit records. A generic mutation endpoint would either weaken those
properties or recreate the current explicit operations inside a second schema,
which gives little net simplification.

The next best step is to improve discoverability of the existing surface:

- Keep capability surfaces as the first filter.
- Add or improve catalog/documentation resources that group tools by workflow
  and state transition.
- Prefer MCP apps/resources for read-heavy dashboards and project context.
- Consider the read-only `query-project-context` prototype only if clients still
  spend too many calls assembling the same cross-artifact context.
