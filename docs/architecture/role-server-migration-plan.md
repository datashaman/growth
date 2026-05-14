# Role-server migration plan — 2026-05-14

Plan for closing out issue #33 (finish the role-server migration; remove flat-tool auto-discovery from `AllServer`). Source data is a fresh `app/Mcp/Tools/` inventory.

## Findings recap

- 34 flat tools have a same-stem twin in a role subdir. Split by kind:
  - **23 shims**: the flat file is `class X extends Role\X {}`. Pure back-compat.
  - **11 parallels**: the flat file and nested file are genuinely different code, sometimes with different response shapes.
- 48 flat tools have no twin anywhere; they need a subdir home.
- **Every role server currently imports from the flat `App\Mcp\Tools\*` namespace**, even when an unused role-dir twin exists. The role subdirs are populated but only `AllServer` auto-discovery references most of them.

## Guiding rule

For every pair (shim or parallel), the **flat version is what's registered and shipping**. Nested versions only run via `AllServer` auto-discovery, which is the surface bloat we're removing. So the safe default is:

> **Keep the flat behavior. Move its body into the correct role subdir. Delete the unregistered nested file. Update every role-server import to the new subdir path.**

This is a pure relocation + namespace rename for parallels. No consumer-visible behavior change, because the flat version was the live one. The nested code that's silently been part of `AllServer` since the migration started is discarded.

Exceptions per pair are flagged in the parallel table below.

## Shim pairs — 23 tools, mechanical

Action for all: **delete the flat shim**, role-dir twin keeps its body, all importers repoint `App\Mcp\Tools\X` → `App\Mcp\Tools\Role\X`.

| Flat | Role-dir twin |
|---|---|
| `AssignRole` | `Plan/AssignRole` |
| `AssignWorkItemRaci` | `Plan/AssignWorkItemRaci` |
| `BaselinePlan` | `Plan/BaselinePlan` |
| `ComparePlanBaseline` | `Plan/ComparePlanBaseline` |
| `DeleteAgent` | `Plan/DeleteAgent` |
| `LinkWorkItemDependency` | `Plan/LinkWorkItemDependency` |
| `LinkWorkItemToMilestone` | `Plan/LinkWorkItemToMilestone` |
| `ListAgents` | `Plan/ListAgents` |
| `ListPlanBaselines` | `Plan/ListPlanBaselines` |
| `ListReviews` | `Reviews/ListReviews` |
| `SummarizeImplementationStatus` | `Plan/SummarizeImplementationStatus` |
| `SummarizePlanCapacity` | `Plan/SummarizePlanCapacity` |
| `SummarizeScheduleHealth` | `Plan/SummarizeScheduleHealth` |
| `UnassignRole` | `Plan/UnassignRole` |
| `UnassignWorkItemRaci` | `Plan/UnassignWorkItemRaci` |
| `UnlinkWorkItemDependency` | `Plan/UnlinkWorkItemDependency` |
| `UnlinkWorkItemFromMilestone` | `Plan/UnlinkWorkItemFromMilestone` |
| `UpsertAgent` | `Plan/UpsertAgent` |
| `UpsertReview` | `Reviews/UpsertReview` |
| `UpsertReviewFinding` | `Reviews/UpsertReviewFinding` |
| `UpsertReviewParticipant` | `Reviews/UpsertReviewParticipant` |
| `UpsertReviewPlan` | `Reviews/UpsertReviewPlan` |
| `UpsertRisk` | `Plan/UpsertRisk` |

## Parallel pairs — 11 tools, decisions needed

Default action: **keep flat body, move into subdir, delete nested twin**. Per-row notes below flag where this is non-obvious.

| Flat | Nested twin | Default target | Notes |
|---|---|---|---|
| `BuildEvidenceBundle` | `Assurance/BuildEvidenceBundle` | `Assurance/BuildEvidenceBundle` | Flat (78L, uses `ReadinessGateEvaluator`) replaces nested (47L, uses `EvidenceBundleBuilder`). **Confirm** the `EvidenceBundleBuilder` service is unreferenced after this change — it likely becomes dead code. |
| `DeleteAnomaly` | `Test/DeleteAnomaly` | `Test/DeleteAnomaly` | Flat (30L) replaces nested (50L). Nested may have extra validation that needs porting; I'll review before deletion. |
| `DeleteProject` | `Projects/DeleteProject` | `Projects/DeleteProject` | **Different response shapes**: flat returns `capabilities_deleted`/`architecture_views_deleted`/`verification_plans_deleted`; nested returns `requirements_deleted`/`design_views_deleted`/`test_plans_deleted`. Flat is registered (ManagementServer + IntakeServer), so consumers expect flat shape. Confirm flat names are correct or pick a clean set. |
| `EvaluateReadinessGates` | `Assurance/EvaluateReadinessGates` | `Assurance/EvaluateReadinessGates` | Default rule applies. |
| `LintBaselines` | `Plan/LintBaselines` | `Plan/LintBaselines` | Flat is 42L, nested 60L. Default rule applies; will review for missing rules in flat. |
| `LintProject` | `Lint/LintProject` | `Lint/LintProject` | Flat (84L, newer 2026-05-14) has the section-grouped response and the "sections.planning matches lint-pmp..." description. Nested (103L, older) has older shape. **Flat clearly canonical.** This is the exact tool issue #38 will later promote, so its richer description matters. |
| `LintReviews` | `Reviews/LintReviews` | `Reviews/LintReviews` | Nested is newer (2026-05-13 vs 2026-05-12). Will verify which has the readiness-prereqs work from PR #27 before deleting. |
| `ListProjects` | `Projects/ListProjects` | `Projects/ListProjects` | Default rule applies. |
| `UpsertRole` | `Plan/UpsertRole` | `Plan/UpsertRole` | Default rule applies. |
| `UpsertSource` | `Sources/UpsertSource` | `Sources/UpsertSource` | Nested is newer (2026-05-13). Will verify recent feature work landed in flat before deleting. |
| `UpsertStakeholder` | `Stakeholders/UpsertStakeholder` | `Stakeholders/UpsertStakeholder` | Nested is newer. Same review as above. |

## Orphan placements — 48 flat tools, new homes

Grouped by domain. Each gets moved to the target subdir and registered explicitly on its role server.

### Architecture (10) → **new `Architecture/` subdir** (distinct from existing `Design/`, which holds the design-view tools)

`DeleteArchitectureElement`, `DeleteArchitectureView`, `DeleteArchitectureViewpoint`, `LintArchitecture`, `ListArchitectureElements`, `ListArchitectureViewpoints`, `ListArchitectureViews`, `UpsertArchitectureElements`, `UpsertArchitectureView`, `UpsertArchitectureViewpoint`

Wired on: `ArchitectureServer`.

### Capabilities (5) → **new `Capabilities/` subdir** (alternative: merge with existing `Requirements/`)

`DeleteCapability`, `LintCapabilities`, `ListCapabilities`, `UpsertCapabilities` (+`LinkWorkItemToCapabilities`/`UnlinkWorkItemFromCapability` below).

**Question**: existing `Requirements/` subdir already holds tools for the same model (`Requirement`). Two options:

- **A** — new `Capabilities/` subdir, leave `Requirements/` for the lint/search/status tools that use the noun "requirement". Matches the tool-name prefix (capability tools start with `Capability`/`Capabilities`).
- **B** — rename `Requirements/` → `Capabilities/`, fold everything in. Matches issue #2's stance: capability is the user-facing noun, requirement is internal.

Recommend **B** for consistency with `rigor_level`/`integrity_level` decision in #2.

### Verification (10) → **new `Verification/` subdir**

`DeleteVerificationCase`, `DeleteVerificationPlan`, `DeleteVerificationRun`, `LintVerification`, `ListVerificationCases`, `ListVerificationPlans`, `ListVerificationRuns`, `LogVerificationRun`, `UpsertVerificationCases`, `UpsertVerificationPlan`

Wired on: `VerificationServer`, `ReadonlyServer` (read-only ones).

### Plan + delivery (8) → existing `Plan/` subdir

`DeletePlan`, `UpsertPlan`, `ListProjectPlans`, `ListCheckRuns`, `UpsertCheckRun`, `ListDeliveryLinks`, `UpsertDeliveryLink`, `LinkWorkItemToCapabilities`, `UnlinkWorkItemFromCapability`, `DeleteDeployment`, `DeleteRelease`

### Test (2) → existing `Test/` subdir

`ListAnomalies`, `UpsertAnomaly`

### Citations (3) → existing `Sources/` subdir

`DeleteCitation`, `ListCitations`, `UpsertCitation`

### Concerns (1) → existing `Concerns/` subdir

`UpsertConcerns` (plural; sibling `Concerns/UpsertConcern` is singular and survives)

### Projects (1) → existing `Projects/` subdir

`UpsertProject`

### Glossary (1)

`LookupTerm` → **decision needed**: existing `Glossary/GlossaryLookup` is a different tool (different MCP name `glossary-lookup` vs `lookup-term`; flat reads "approved internal project glossary", nested reads "user-provided glossary extract"). Both are wanted? Keep both and move `LookupTerm` into `Glossary/`?

### Dashboard / cross-cutting (4)

`GetProjectDashboardData`, `ShowProjectDashboard`, `BulkLink`, `WhoAmI` → **decision needed**:

- `WhoAmI` is on every role server. Options: (a) new `Common/WhoAmI` subdir, (b) leave at flat level as a documented exception. Recommend **(a)**, makes the "no flat tools" invariant total.
- `BulkLink` is on `ArchitectureServer` + `PlanningServer`. Options: `Plan/BulkLink`, `Common/BulkLink`, or duplicate. Recommend `Common/BulkLink`.
- Dashboard tools: new `Dashboard/` subdir or fold into `Trace/`. Recommend new `Dashboard/`.

## AllServer rewrite

After all tools live in subdirs, `AllServer::boot()` switches from `discoverPrimitives()` (file-walking) to a flat union of the role-server tool arrays. Trace tools and `WhoAmI` deduplicate. This is the smallest possible diff once the underlying moves land.

## Suggested slicing

This is mechanically large. Two ways to ship:

1. **One big PR** (~120 files): all 23 shim deletions + 11 parallel relocations + 48 orphan moves + 6 role-server import rewrites + `AllServer` rewrite. Reviewable as one logical change ("finish the migration") but heavy diff.
2. **Slice by risk**:
   - **F1** — Shim cleanup. Delete 23 flat shims, repoint imports. ~50 files. No behavior change possible (shims have no body).
   - **F2** — Parallel relocations. Per-pair decisions baked in. ~30 files. Behavior change risk where nested-version features get dropped.
   - **F3** — Orphan moves. 48 file moves + role-server registrations. ~100 files. No behavior change (just file location + namespace).
   - **F4** — `AllServer` switch + remove auto-discovery + document per-role counts. Small, depends on F1–F3.

Recommend F1+F4 split (shims first, AllServer last), F2 and F3 in the middle, possibly bundled depending on how the parallel decisions feel.

## Open decisions

Before any code changes, confirm:

1. Default rule for parallel pairs (keep flat behavior, move to subdir, delete nested)? Y/N
2. `Capabilities/` subdir option A or B?
3. `LookupTerm` vs `GlossaryLookup` — keep both? Where does `LookupTerm` go?
4. `WhoAmI` and `BulkLink` placement — `Common/` subdir, top-level exceptions, or duplicates?
5. Slicing — one big PR or F1/F2+F3/F4 split?
