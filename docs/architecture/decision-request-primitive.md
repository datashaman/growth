# Decision Request: Asking a Role to Decide — 2026-05-18

Design decision for issue #279. Status: **accepted — introduce a standalone
`DecisionRequest` primitive, routed to a project `Role`, with a polymorphic
optional subject, an option set, and a lifecycle on the generic
`status_transitions` machinery.**

## The problem

The dominant real-world pattern in a project is "role A needs role B to
decide" — engineering needs the product owner to choose on a change request.
Today that means raising a change request and *hoping* a notification is
noticed. There is no durable, addressed, answerable clarifying question.

A decision request fills that gap: a requester asks a named role a question,
attaches the options to choose between and an optional deadline; it lands in
that role's queue; an assignee answers by choosing an option with a rationale;
the requester is notified of the answer.

## Why a new primitive, not a change request or a review

| | Change request | Review | Decision request |
|---|---|---|---|
| Question shape | "approve this change" | "assess this artifact" | "choose between these options" |
| Routed to | a CCB / approver | review participants | one named role |
| Answer | a decision + approval event | findings + a decision | one chosen option + rationale |
| Stands alone | no — always about a change | no — always about a target | **yes** — may reference nothing |

A decision request is a person-to-person clarifying question. Forcing it
through the change-request machinery would mean inventing a change for every
question; forcing it through reviews would mean a review with no artifact.
The issue is explicit: it is "distinct from a change request." So it is its
own primitive — but it deliberately reuses the shared transition machinery
rather than reinventing an audit log.

## The shape

`decision_requests` — project-scoped (`ScopedByOwner`), ULID-keyed:

- `project_id` — the owning project (workspace resolves through it).
- `requester_user_id` — the person who asked; nullable for an agent caller.
- `target_role_id` — the `Role` being asked. Routing is to a role, not a
  person: roles outlive the individuals filling them.
- `question` — the decision to be made.
- `status` — `open` → `answered` / `expired` / `cancelled`.
- `deadline` — optional; when passed, an open request becomes `expired`.
- `subjectable_type` / `subjectable_id` — **optional** polymorphic link to the
  artifact the decision is about (a change request, a requirement, …). Null
  for a free-standing question.
- `chosen_option_id`, `answer_rationale`, `answered_by_user_id`,
  `answered_at` — the answer, all null until answered.

`decision_request_options` — the choices, ULID-keyed, one row per option, so
the answer can name a stable option id rather than a string or array index.

### Routing decisions (from #279 triage)

- **Targets a `Role` record, not an `OperatingRole`.** The question is
  "ask the product owner", and the product owner is project planning data —
  a `Role`. A session is not bound to a `Role`, so "my queue" is defined
  below rather than read off the session.
- **The queue** (`list-decision-queue`) defaults to decision requests
  targeting any role the calling user is assigned to (`User::roles()`, the
  `assignables` pivot), and accepts an optional `role_id` to inspect one
  specific role's queue.
- **Any assignee of the target role may answer.** Creating a decision
  request notifies every assignee of the target role; answering notifies the
  requester. The target role is an authorisation boundary, not advisory.
- **The subject link is optional and polymorphic** — a decision request can
  point at an existing artifact or stand fully alone.

### Lifecycle on the generic machinery

The lifecycle is simple — one open state, three terminal states — so it uses
the existing verb-named `Transition` classes and the polymorphic
`status_transitions` table (as work items, risks, and anomalies do), not a
bespoke event table like `change_approval_events`. The chosen option and
rationale live on the `decision_requests` row; the transition row records the
status move, the actor, and the acting role.

- `AnswerDecisionRequest` — `open → answered`; requires a rationale and a
  chosen option; stamps the answer columns; notifies the requester.
- `CancelDecisionRequest` — `open → cancelled`.
- `ExpireDecisionRequest` — `open → expired`; driven by a scheduled
  `decision-requests:expire` command over requests past their deadline.

## MCP surface

Four tools, registered on every role server (the comms primitives — feedback,
and now decision requests — are cross-cutting, not role-scoped):

- `create-decision-request` — question, target role, ≥2 options, optional
  deadline, optional subject.
- `list-decision-queue` — the caller's queue (assigned roles, or an explicit
  `role_id`).
- `answer-decision-request` — choose an option with a rationale.
- `cancel-decision-request` — withdraw an open request.

## Out of scope for this slice

A webapp surface for decision requests, and the what-needs-me digest (#280),
which will read decision queues among other sources.
