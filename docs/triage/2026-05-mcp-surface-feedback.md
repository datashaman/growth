# MCP Surface Feedback Triage — 2026-05-14

Source: power-user session report on the Growth MCP tool surface. Items are
grouped by the reporter's priority bands; rows note effort, status, and any
verification findings from the codebase.

## Cross-cutting context

The repo now exposes seven role-scoped capability surfaces (`management`,
`intake`, `architecture`, `planning`, `verification`, `governance`, and
`readonly`) plus `all`. `AllServer` is a deduplicated union of the role servers,
not filesystem auto-discovery, so it is the intended power-user surface rather
than a migration artifact. Older notes below that mention double-registration or
six role servers should be read as historical context.

Status legend: **accept** = file an issue; **defer** = file an issue but
de-prioritize; **already-planned** = covered by in-flight work; **reject** =
won't act on, with rationale.

## High impact

| # | Item | Effort | Status | Issue | Notes |
|---|------|--------|--------|-------|-------|
| 1 | Document rigor semantics in `upsert-project` description, not just the range | S | **accept** | [#30](https://github.com/datashaman/growth/issues/30) | First slice. |
| 2 | Pick one name: `rigor_level` vs `integrity_level` | S | **accept** | [#31](https://github.com/datashaman/growth/issues/31) | Verified: `UpsertProject` exposes `rigor_level` (maps to `integrity_level` internally); `Projects/CreateProject` and `Projects/UpdateProject` expose `integrity_level` directly. Storage column is `integrity_level`. |
| 3 | Ship public-guidance bodies, or drop the search tool | M | **already-planned** | [#32](https://github.com/datashaman/growth/issues/32) | Per `docs/architecture/public-guidance-corpus.md`, public guidance extraction is intentionally not MCP-facing. Re-add only through an explicit role-server registration. |
| 4 | Collapse the CRUD-per-entity sprawl | L | **defer** | [#33](https://github.com/datashaman/growth/issues/33) | The surface is now role-organized and `AllServer` is deduplicated, but the entity-level CRUD shape remains broad. Consolidate around concrete workflow pressure rather than migration cleanup. |
| 5 | Declarative bulk-import (`apply-manifest` / `export-manifest`) | L | **shipped** | [#34](https://github.com/datashaman/growth/issues/34) | Exposed on `management` and `all`; manifest resources are also available. |

## Medium impact

| # | Item | Effort | Status | Issue | Notes |
|---|------|--------|--------|-------|-------|
| 6 | Standardize lint naming (`pmp-lint` vs `lint-X`) | S | **accept** | [#35](https://github.com/datashaman/growth/issues/35) | Verified: `Plan/PmpLint.php` is the only outlier. |
| 7 | Apply destructive-confirm guard consistently | S | **accept** | [#36](https://github.com/datashaman/growth/issues/36) | Verified: only `DeleteProject` requires `confirm_name`. |
| 8 | Surface lifecycle status on the project itself | M | **accept** | [#37](https://github.com/datashaman/growth/issues/37) | New `status` enum, gate writes on archived. |
| 9 | Make per-section linters a `lint-project --sections=[…]` filter | M | **defer** | [#38](https://github.com/datashaman/growth/issues/38) | Blocked by #4. |
| 10 | Expand `who-am-i` payload | S | **accept** | [#39](https://github.com/datashaman/growth/issues/39) | Owned projects, default, last-touched, roles. |

## Nice-to-have

| # | Item | Effort | Status | Issue | Notes |
|---|------|--------|--------|-------|-------|
| 11 | Starter manifests as resources (`growth://template/rigor-N`) | M | **shipped** | [#40](https://github.com/datashaman/growth/issues/40) | Rigor-level starter templates are exposed on the management surface. |
| 12 | More embeddable MCP-app dashboards | L | **partly shipped** | [#41](https://github.com/datashaman/growth/issues/41) | Project dashboard, gate status, requirement explorer, and trace graph apps exist; add more on concrete demand. |
| 13 | Subscriptions / watches on `check_run.failed` etc. | L | **defer** | [#42](https://github.com/datashaman/growth/issues/42) | Blocked by MCP SDK. |
| 14 | Make `trace-query` the recommended entry point | S | **accept** | [#43](https://github.com/datashaman/growth/issues/43) | Docs only. |
| 15 | Document cascade behavior on `delete-*` tools | S | **accept** | [#44](https://github.com/datashaman/growth/issues/44) | Pairs with #7. |

## Selected first slice

**Item #1** — embed the rigor-level rule-activation table inline in the
`UpsertProject` tool description so model clients learn the spec from the
schema instead of having to diff `evaluate-readiness-gates` outputs.

Plan to follow in a separate doc / issue.
