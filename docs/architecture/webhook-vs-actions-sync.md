# GitHub Webhook Receiver vs Actions-Based Sync — 2026-05-17

Design spike for issue #175. Status: **recommendation made — keep the action-based
model; defer the webhook receiver. No implementation issues spun off.**

## Recommendation

Keep the current Actions-based sync model. Do **not** build a webhook receiver now.

The webhook pivot trades a *cosmetic* cost (per-event runs in the adopter's Actions
tab) for *structural* cost (a public endpoint, signature verification, a queue,
dedup, a published GitHub App, and a new install-time workspace binding). That trade
is only worth making when Growth needs events the Actions model **cannot reach** —
not to tidy a busy Actions tab. We are not there yet, and nothing tracked says we
are. Revisit when the trigger below fires.

If the Actions clutter becomes a real adopter complaint before then, the cheap
mitigation — trim default triggers, batch events — closes most of the gap without
an architecture change.

### Revisit trigger

Build the webhook receiver when an adopter needs an event the Actions model
structurally cannot deliver. `docs/architecture/github-integration.md` already names
the v2 list: **issue comments, review submissions on draft PRs, and label changes
made by bots** — events that either do not run workflows or run them without the
context the action needs. The first concrete request for one of those is the signal.

## Why now is not the time

- **The cost is cosmetic, not functional.** The Actions model already delivers
  every event Growth consumes today (branch attribution, check evidence,
  deployments, releases). The only complaint is visual clutter in the adopter's
  Actions tab.
- **The clutter complaint is self-reported, not adopter-reported.** Issue #175 is
  the *only* tracked mention of Actions clutter — no adopter feedback, no
  `tool_feedback` entry. Spending a structural change on an unvalidated cosmetic
  concern is premature.
- **The dogfooding-stop signal points the same way.** #167 ("Stop dogfooding
  growth-sync on this repository") removed the workflow from this repo. The commit
  carries no rationale, but the effect is telling: even the team felt the per-event
  runs were noisy enough to switch off on its own repo. That is evidence the
  *clutter* is real — and equally evidence that the cheap mitigation (turning
  triggers off) is sufficient, since that is exactly what #167 did.
- **The webhook model adds standing infrastructure** Growth does not run today: a
  public unauthenticated endpoint, HMAC signature verification, a delivery queue,
  idempotency/dedup storage, replay handling, and a published GitHub App with an
  installation lifecycle. Each is a maintenance and security surface.

## The five questions

### 1. Can a webhook deliver everything the action does?

Functionally yes, with one shift. The action does enrichment *before* calling Growth
— fetches commit messages via the GitHub REST API, parses `Growth-Work-Item:`
trailers and `WI-NNN` branch references, then resolves work items and posts a
`Growth: work item attribution` check-run back to the PR. A webhook payload does not
carry commit messages or the resolved trailer, so that enrichment must move
**server-side**: the receiver would call the GitHub REST API itself (using the
GitHub App's installation token) to fetch what the payload omits.

That is feasible but not free — it relocates GitHub API calls, rate-limit handling,
and the check-run write into Growth. The check-run write in particular requires the
App to hold `checks: write`, which the adopter currently grants per-repo via the
workflow's `permissions:` block (see `workflow.example.yml`); under the App model it
becomes a permission the adopter approves at install time.

### 2. GitHub App distribution and installation

A published GitHub App, installable from the Marketplace or a direct install URL.
The adopter installs it on selected repos/orgs; GitHub then posts events to Growth's
configured webhook URL. This replaces the current "copy a workflow file + set a
`GROWTH_TOKEN` secret" onboarding with an OAuth-style install flow. It is a better
adopter experience — but it is a product surface Growth must build, publish, and
maintain, including the App's permission set and its review by GitHub.

### 3. Auth: mapping a webhook event to a workspace/project

**This is the hard problem.** Today the adopter's `GROWTH_TOKEN` is a Passport
access token with a `workspace_id` binding — the workspace is carried *by the
credential*. A webhook payload carries only `repository` and `installation_id`; it
has no Growth credential and no workspace.

The receiver therefore needs a new install-time record mapping
`installation_id → workspace_id`, written when the adopter completes the GitHub App
setup callback (the callback is the one moment an authenticated Growth user and a
fresh `installation_id` are present together). Every inbound webhook then resolves
its workspace via that record, and its project via the existing
`resolve-project-by-repo` path keyed on `repository`. Building and securing that
mapping table — plus the setup-callback flow that populates it — is a substantial
slice on its own and is the main reason the webhook model is not a quick win.

### 4. Dedup, retry, replay

GitHub delivers webhooks at-least-once and retries on non-2xx. The receiver must be
idempotent: persist each delivery's `X-GitHub-Delivery` GUID and short-circuit
repeats. Processing must be async — acknowledge 2xx fast, enqueue, process on a
worker — so a slow Growth call does not trigger GitHub retries. None of this exists
today; the Actions model gets dedup for free because each workflow run is a single
discrete invocation. This is net-new infrastructure the webhook model must carry.

### 5. Migration path for existing adopters

Both models can run concurrently — they are independent ingestion paths writing the
same data. Migration is per-adopter and non-breaking: install the App, then remove
the workflow file. The `installation_id ↔ workspace_id` record (Q3) makes the App
path self-sufficient; the action path keeps working until the adopter removes it.
No data migration, no flag day. This is the one genuinely easy part — and it means
deferring the decision costs nothing, since adopters can move later without rework.

## Clutter vs capability — two different axes

The spike is easy to get wrong by conflating two separate things:

- **Clutter (cosmetic).** Per-event Actions runs crowd the adopter's Actions tab.
  Fixable *within* the current model — trim default triggers, batch events. No
  architecture change. This is what #175's Context section leads with, and what #167
  effectively addressed by switching the workflow off.
- **Capability (structural).** Events the Actions model cannot reach at all —
  issue comments, draft-PR review submissions, bot label changes. *Only* a webhook
  receiver fixes this. This is the real reason to build it.

The webhook receiver is justified by the **capability** axis, never the clutter
axis. Building it to reduce clutter is using a structural change to solve a cosmetic
problem — exactly the trade this recommendation declines.

## Non-goals

- Deciding the webhook receiver's internal design. If the revisit trigger fires, a
  fresh design doc covers the receiver, queue, dedup store, and the
  `installation_id ↔ workspace_id` mapping in detail.
- Removing or changing the current `actions/growth-sync/` action. It stays as the
  supported integration path.

## Follow-up

None. Per the spike's own terms, implementation issues are spun off **only if the
webhook model is chosen**. It is not chosen, so no issues are filed. The revisit
trigger above is the re-entry point.
