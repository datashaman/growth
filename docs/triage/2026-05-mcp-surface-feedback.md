# MCP Surface Feedback Triage — 2026-05-14

Source: power-user session report on the Growth MCP tool surface. Items are
grouped by the reporter's priority bands; rows note effort, status, and any
verification findings from the codebase.

## Cross-cutting context

The repo is mid-migration from a flat `app/Mcp/Tools/*.php` surface to
role-organized subdirectories (`Tools/Projects/`, `Tools/Lint/`, `Tools/Plan/`,
etc.) exposed via six role servers (`intake`, `architecture`, `planning`,
`verification`, `governance`, `readonly`). The reporter was talking to
`AllServer`, which auto-discovers everything under `app/Mcp/Tools/` — so it
sees both the old flat tools and the new role-scoped ones simultaneously,
roughly double-counting the real surface. Several items below are partly
"finish the role-server migration and stop registering the legacy tools in
`AllServer`."

Status legend: **accept** = file an issue; **defer** = file an issue but
de-prioritize; **already-planned** = covered by in-flight work; **reject** =
won't act on, with rationale.

## High impact

| # | Item | Effort | Status | Issue | Notes |
|---|------|--------|--------|-------|-------|
| 1 | Document rigor semantics in `upsert-project` description, not just the range | S | **accept** | [#30](https://github.com/datashaman/growth/issues/30) | First slice. |
| 2 | Pick one name: `rigor_level` vs `integrity_level` | S | **accept** | [#31](https://github.com/datashaman/growth/issues/31) | Verified: `UpsertProject` exposes `rigor_level` (maps to `integrity_level` internally); `Projects/CreateProject` and `Projects/UpdateProject` expose `integrity_level` directly. Storage column is `integrity_level`. |
| 3 | Ship public-guidance bodies, or drop the search tool | M | **already-planned** | [#32](https://github.com/datashaman/growth/issues/32) | Per `docs/architecture/public-guidance-corpus.md`, intentionally unregistered on role servers but leaks back via `AllServer` auto-discovery. |
| 4 | Collapse the CRUD-per-entity sprawl | L | **defer** | [#33](https://github.com/datashaman/growth/issues/33) | Headline ~180 inflated by double-registration. Per-role counts: 19–53. Finish migration first. |
| 5 | Declarative bulk-import (`apply-manifest` / `export-manifest`) | L | **accept** | [#34](https://github.com/datashaman/growth/issues/34) | Design doc before sub-tasks. |

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
| 11 | Starter manifests as resources (`growth://template/rigor-N`) | M | **accept** | [#40](https://github.com/datashaman/growth/issues/40) | Blocked by #5. |
| 12 | More embeddable MCP-app dashboards | L | **defer** | [#41](https://github.com/datashaman/growth/issues/41) | Add on concrete demand. |
| 13 | Subscriptions / watches on `check_run.failed` etc. | L | **defer** | [#42](https://github.com/datashaman/growth/issues/42) | Blocked by MCP SDK. |
| 14 | Make `trace-query` the recommended entry point | S | **accept** | [#43](https://github.com/datashaman/growth/issues/43) | Docs only. |
| 15 | Document cascade behavior on `delete-*` tools | S | **accept** | [#44](https://github.com/datashaman/growth/issues/44) | Pairs with #7. |

## Selected first slice

**Item #1** — embed the rigor-level rule-activation table inline in the
`UpsertProject` tool description so model clients learn the spec from the
schema instead of having to diff `evaluate-readiness-gates` outputs.

Plan to follow in a separate doc / issue.
