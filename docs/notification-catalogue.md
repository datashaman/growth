# Workspace notification catalogue

The channel-agnostic catalogue of events that warrant a notification — something
a recipient genuinely wants to know about asynchronously, not every state change.

Each event is one `App\Notifications\*` class extending `WorkspaceNotification`.
The same catalogue is consumed by the webapp sinks (`database` for the bell
inbox, `broadcast` for live arrival) and, once the substrate lands, the MCP sink
(#42) — the `mcp` channel appends to `WorkspaceNotification::via()` without
reshaping any event class.

## Events

| Event | Notification class | Payload | Recipients | Emitted at |
| --- | --- | --- | --- | --- |
| `role.assigned` | `RoleAssigned` | the role | the assigned user (personal) | `AssignRole` tool, on a new user attachment |
| `change_request.decided` | `ChangeRequestDecided` | the change request + its decision | workspace members, minus the actor | Approve / Reject / Defer change-request transitions |
| `project.status_changed` | `ProjectStatusChanged` | the project + its new status | workspace members, minus the actor | Activate / Archive / Close / Restore project transitions |
| `review.held` | `ReviewHeld` | the review | workspace members, minus the actor | `HoldReview` transition |
| `anomaly.opened` | `AnomalyOpened` | the anomaly + its severity | workspace members, minus the actor | `UpsertAnomaly` tool, on creation |

## Justification

- **Personal events** (`role.assigned`) go to one named user — being given a
  role is something only that person needs to act on.
- **Lifecycle events** go to every workspace member because they change the
  shared state of the workspace's work; the actor who caused the change is
  excluded since they already know.
- Transition-sourced events are emitted from `Transition::apply()` *after the
  transaction commits* — a rolled-back transition notifies no one.
- Only *decision* change-request transitions notify; lifecycle-only moves
  (submit, implement, cancel) do not. Only anomaly *creation* notifies, not
  updates.

## Deliberately excluded

- **Gate / readiness regressions** — a readiness gate regressing to failing is
  a genuine candidate, but readiness gates are computed on demand and no gate
  state is persisted. Detecting a regression needs a gate-state snapshot table
  and a pass→fail comparison; that is its own slice, deferred to a follow-up.
- **RACI assignment** — RACI assigns a *role* (not a user) to a work item; the
  human recipients are whoever fills that role, an indirection left for later.
- **Routine state changes** — start/complete/block work items, deployments,
  test runs: high-frequency, low-signal, and better seen on their own pages.
