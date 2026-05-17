# Agent-Paced Planning: Retiring the Schedule Axis — 2026-05-17

Design spike for issue #182. Status: **recommendation made — replace the time
axis with a dependency-ordered scope graph scored by gate satisfaction; drop the
date / effort / capacity schema and the schedule and capacity tooling; keep
baselines, the WBS, the dependency graph (collapsed to one edge kind), and
milestones (reframed as scope bundles). Five implementation issues spun off (see
end).**

## Recommendation

An agent-paced plan **is a dependency-ordered scope graph, and progress is
measured by gate satisfaction over that graph — not by elapsed time against
dates.**

The plan stays a Work Breakdown Structure: a tree of work items ordered by a
precedence DAG. What changes is the *measure*. Today the plan answers "are we on
schedule?" by comparing `today` against `due_date` and `target_date`. An
agent-paced plan answers "what is ready to work, and what is left to satisfy?" —
the bottleneck is scope definition, dependency ordering, and gate satisfaction,
exactly as the issue states. Those three become the organising axis; time stops
being an axis at all.

Concretely:

- **Ordering replaces scheduling.** The work-item dependency graph is the spine.
  "Next" is "every dependency satisfied"; there is no "late".
- **Scope completeness replaces velocity.** Progress is the fraction of defined
  scope that has passed its gates, not work done per unit time.
- **Gate satisfaction replaces schedule health.** A readiness gate already
  encodes "is this phase done enough"; it becomes the progress signal.

Do **not** keep the date / effort / capacity columns as nullable-and-ignored.
Time coupling is pervasive enough that a half-cutover leaves dead fields an agent
will still dutifully populate and the dashboard will still surface. Recommend a
clean cutover — consistent with the project's no-deprecation-shims rule: drop the
schema and the tooling together.

## What "time-coupled" covers — the audit

The schedule axis is not one feature; it is threaded through the plan. The audit
below is the scope of the cutover. "Verdict" anticipates Q3.

| Surface | Time coupling | Verdict |
|---|---|---|
| `work_items.planned_start_date`, `due_date` | scheduling | remove |
| `work_items.effort_estimate(_hours)`, `effort_actual(_hours)` | duration | remove |
| `work_items.cost_*` (`cost_estimate_amount`, `cost_actual_amount`, `cost_currency`, labels) | cost as `hours × rate` | remove — see *Cost* below |
| `milestones.target_date` | dated checkpoint | remove |
| `Milestone` status `missed`, `deferred` | date-driven outcomes | remove |
| `work_item_dependencies.kind` (4 kinds) | 3 of 4 are duration-relational | collapse to 1 |
| `ProjectPlanBaseline.snapshot` date / hour / cost keys | snapshot of the above | new baselines omit them |
| `roles.weekly_capacity_hours`, `hourly_rate_amount`, `rate_currency` | capacity & rate | remove |
| `ScheduleHealthSummarizer` | overdue + date-risk findings | mostly remove; the ordering check survives |
| `PlanCapacitySummarizer` | utilisation = `effort ÷ capacity` | remove |
| `SummarizeScheduleHealth`, `SummarizePlanCapacity` MCP tools | the two summarizers | remove |
| `PmpLinter` date rules (`pmp.milestone.no_date`, `past_pending`, `could_miss`, `pmp.schedule.*`) | milestone dates, overdue items | remove |
| Dashboard **Schedule** and **Capacity** panels | the two summarizers | remove |
| `planning` readiness gate (folds in schedule health) | `ScheduleHealthSummarizer` | reframe |

Untouched: the WBS hierarchy and work-item status enum, the dependency graph as
a structure, `ProjectPlanBaseline` as a change-control mechanism, the `Plan`
lifecycle, the structural WBS lint rules, and every linter that is not
date-coupled (`RequirementLinter`, `DesignLinter`, `TestLinter`, `ReviewLinter`,
`ChangeLinter`, the structural half of `PmpLinter`).

## The four questions

### 1. If time is no longer the organising axis, what is?

**The dependency-ordered scope graph, scored by gate satisfaction.**

The issue offers four candidates — dependency/readiness ordering, scope
completeness, gate satisfaction, evidence coverage. They are not four rival axes;
they are a spine and three measures over it:

- **Spine — dependency ordering.** The work-item precedence DAG already exists
  (`work_item_dependencies`). It is the only structure that says, without
  reference to a clock, *what can be worked now*: the ready frontier is every
  work item whose dependencies are all `done`. This is the planning spine.
- **Measure — scope completeness.** What fraction of the project's defined scope
  (work items, requirements, architecture elements) exists and is linked. This
  answers "how much is left to define".
- **Measure — gate satisfaction.** The readiness gates already answer "is this
  phase done enough". This answers "how much is left to satisfy".
- **Evidence coverage** is a component of gate satisfaction (the verification and
  review gates already consume it), not a separate axis.

What this buys over dates: "what should the agent do next?" has a graph answer —
the ready frontier — instead of a calendar answer. And "is the project in
trouble?" becomes "are there dependency cycles, unsatisfiable gates, or
undefined scope" — all real — instead of "is a date in the past", which for an
agent that produces a batch of work items in moments is pure noise.

### 2. Which schema and tooling become vestigial?

Everything in the audit table marked *remove* or *collapse*. The list is there;
two entries deserve a note.

**The dependency `kind` enum.** `work_item_dependencies.kind` carries
`finish_to_start`, `start_to_start`, `finish_to_finish`, `start_to_finish`. Three
of the four only mean something when work items have *durations that overlap on a
timeline*. Without durations, the only expressible relation is "X must be done
before Y" — `finish_to_start`. Collapse `kind` to a single precedence edge and
drop the enum: a dependency simply *is* a precedence edge.

**The `planning` readiness gate** is not vestigial — it is reframed (Q3). It
stops folding in `ScheduleHealthSummarizer`; its gate *semantics* (pass / warn /
fail for "the plan is ready") are unchanged, only its inputs change.

### 3. What is retained, reframed, or removed?

**Retained, unchanged:**

- The **WBS** — work items, parent/child hierarchy, the status enum
  (`todo`, `in_progress`, `blocked`, `done`, `cancelled`).
- The **dependency graph** as a structure (now one edge kind).
- **`ProjectPlanBaseline`** as a change-control checkpoint. A baseline still
  snapshots scope, still drives `PlanBaselineDiffer`, `BaselineLinter`, and
  change-coverage analysis. It snapshots *fewer fields* — that is the only
  change, and it is covered in Q4.
- The **`Plan` lifecycle** `draft → baselined → active → closed`. "Baselined"
  means "scope is fixed enough that further drift is change-controlled" — a
  statement about change control, not about a schedule. It survives intact.
- **Structural WBS lint** — `pmp.wbs.empty`, `pmp.wbs.flat`, `pmp.wbs.cycle`.

**Reframed:**

- **`Milestone`** — from "dated checkpoint" to "named scope bundle with a gate":
  a set of work items that together deliver something. `target_date` is removed;
  status collapses to `pending → achieved`, where *achieved* means every member
  work item is `done` and the milestone's gate passes. `HitMilestone` survives
  (rename to `AchieveMilestone` for honesty). `MissMilestone` and
  `DeferMilestone` are removed — with no date, there is nothing to miss and
  nothing to defer.
- **`ScheduleHealthSummarizer`** — its ordering finding survives, its date
  findings do not. `schedule.dependency.open` (a work item `in_progress` while a
  dependency is unfinished) is pure ordering integrity, no dates involved — keep
  it. `schedule.milestone.overdue`, `schedule.work_item.overdue`, and
  `schedule.dependency.date_risk` all compare against `today` — remove them. What
  remains is an *ordering-integrity* check; rename the class accordingly or fold
  it directly into the planning gate.
- **The `planning` readiness gate** — stops consuming schedule health; instead
  checks WBS non-empty and acyclic, no orphaned precedence, scope defined, and
  RACI / responsibility coverage. Same gate, ordering-and-scope inputs.

**Removed:** every audit-table row marked *remove* — the date / effort /
capacity columns, the two summarizer-backed MCP tools, the Schedule and Capacity
dashboard panels, and the date-based `PmpLinter` rules.

### Cost — a deliberate carve-out

It is tempting to write "capacity dies as a time lens but survives as a cost
lens". Resist it. `cost_estimate_amount` today is, by `PlanCapacitySummarizer`'s
own formula, `effort_estimate_hours × hourly_rate_amount` — it is a *projection
of the time model*, not an independent axis. Retiring the time model and keeping
its projection would leave a number nothing computes.

An agent's *real* cost — tokens, compute — is a different quantity on a different
axis, measured per invocation, and Growth already records `tool_invocations`.

**Recommendation: remove `cost_*` and the `Role` rate fields with the rest of
the time model.** If agent-cost accounting is wanted, it is a separate feature
built on `tool_invocations`, not a survivor of plan capacity — and it is
explicitly out of scope for this spike (see *Out of scope*).

### 4. Migration — existing dated milestones and baselines

One cutover, no shims:

- **Live tables.** Drop the date / effort / capacity columns. Existing values
  are not migrated anywhere — they describe a model being retired, and there is
  no agent-paced field they map onto.
- **Historical baseline snapshots.** Left as-is. `ProjectPlanBaseline.snapshot`
  is an immutable JSON blob; old snapshots keep their `due_date`, `effort_*`, and
  `cost_*` keys. `PlanBaselineDiffer` is updated to compare only the fields the
  new model defines and to *ignore* extra keys it finds in an old snapshot. No
  snapshot is rewritten; new snapshots simply never write the retired keys. This
  keeps historical baselines readable without keeping the schema alive.
- **Dated milestones.** A milestone loses its `target_date` column; rows in the
  removed `missed` or `deferred` statuses migrate to `pending` (the only
  non-achieved state in the new enum). The named checkpoint survives; its date
  does not.

This is the full-cutover path the project's "no deprecation shims" rule
prescribes — the only concession is that immutable historical blobs are read
tolerantly rather than rewritten.

## Why this shape

- **Honest about the bottleneck.** For an agent, elapsed time is not the
  constraint; scope, ordering, and gates are. The model now measures what
  actually limits progress.
- **One spine, not two measures competing.** Dependency ordering is the spine;
  scope completeness and gate satisfaction are measures over it. No rival axes.
- **Baselines and change control are untouched** — they were never about time,
  only about controlling drift.
- **A clean cutover.** No nullable-and-ignored columns for an agent to fill with
  meaningless dates, no dead panels.
- **Cost is named, not fudged.** It is deliberately removed and its possible
  future scoped out, rather than silently kept as a vestigial projection.

## Implementation issues to spin off

1. **Drop the schedule schema.** Migration removing `planned_start_date`,
   `due_date`, `effort_*`, and `cost_*` from `work_items`; `target_date` from
   `milestones`; `weekly_capacity_hours`, `hourly_rate_amount`, `rate_currency`
   from `roles`. Collapse `work_item_dependencies.kind` to a single precedence
   edge and drop the enum. Update models, factories, the manifest
   import/export, and the input schemas of `upsert-work-items`,
   `upsert-milestone`, and `upsert-role`.
2. **Retire the schedule and capacity tooling.** Remove the date findings from
   `ScheduleHealthSummarizer` (keep and rename the ordering-integrity check),
   delete `PlanCapacitySummarizer`, delete the `SummarizeScheduleHealth` and
   `SummarizePlanCapacity` MCP tools, and delete the date-based `PmpLinter`
   rules. Reframe the `planning` readiness gate onto ordering / scope / RACI.
3. **Reframe `Milestone`.** Status enum → `pending` / `achieved`. Remove the
   `MissMilestone` and `DeferMilestone` transitions; keep `HitMilestone`, renamed
   `AchieveMilestone`. Define *achieved* as "all member work items done and the
   milestone gate passes".
4. **Slim the baseline snapshot.** New `ProjectPlanBaseline` snapshots omit the
   date / effort / cost keys; update `PlanBaselineDiffer` and the snapshot shape
   to ignore those keys when reading historical blobs.
5. **Dashboard.** Remove the **Schedule** and **Capacity** panels. This is the
   change #173 is downstream of: #173's lens-to-panel mapping must drop both
   panels — no lens shows them — and may introduce an ordering / readiness panel
   in their place.

Agent-cost accounting (token / compute spend) is **not** in this list — the
*Cost* carve-out scopes it out as a separate, later track built on
`tool_invocations`.

## Out of scope

- **Agent-cost accounting** as a positive feature — a separate track on
  `tool_invocations`, not a survivor of plan capacity.
- **The read-only-webapp question** — owned by #185.
- **Renaming `PmpLinter`** or the "Project Management Plan" terminology — the
  linter is reframed in place; a rename is cosmetic and independent.
- **The agent-as-role binding** — #183, already landed.

Part of the "agent-operated system of record" repositioning, alongside #183
(agent-as-role), #185 (the reporting-shell webapp), and #184 (confirmation
policy).
