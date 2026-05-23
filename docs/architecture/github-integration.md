# GitHub Integration — Discovery — 2026-05-15

Design note for issue #82. Status: **v1 implemented as the `growth-sync`
GitHub Action and `scaffold-github-sync` MCP tool. Kept as the design record;
the README documents current setup and attribution behavior.**

## Context

Growth's data model is already GitHub-shaped:

- `check_run_evidences.conclusion` enum values are exactly the GitHub Check Run API conclusions (`success`, `failure`, `cancelled`, `skipped`, `neutral`, `timed_out`, `action_required`).
- `check_run_evidences.status` enum (`queued`, `in_progress`, `completed`) matches GitHub's Check Run lifecycle.
- `work_item_delivery_links.type` is `commit | pull_request | branch` — GitHub's three primary delivery artefacts.
- MCP tools `upsert-check-run`, `upsert-deployment`, `upsert-delivery-link` already accept payloads in that shape.

The v1 GitHub integration uses no new delivery-domain tables: the Action
translates GitHub events into the existing MCP tool surface and stores provider
data on the existing delivery, check, deployment, release, and unattributed
event records.

## Implemented first slice

**Pull, not push: a GitHub Action that calls Growth's MCP server.**

The repo being tracked installs `.github/workflows/growth-sync.yml`. The
workflow fires on `pull_request`, third-party `check_run`, GitHub Actions
`workflow_run`, `deployment_status`, and `release` events, then POSTs
translated payloads to `https://growth.../mcp/all` using a Passport token
stored as a repo secret (`GROWTH_MCP_TOKEN`) with `mcp:use` scope.

Why this over a webhook receiver:

- **No new endpoints, no new auth.** The Passport/`mcp:use` flow already works.
- **No signature verification, secret-rotation UI, or replay protection** to build.
- **Workflow logs in GitHub are the audit trail** — failures are visible to the repo owner immediately.
- **The repo, not Growth, owns the credential** — uninstalling is `rm growth-sync.yml`.

Tradeoff: events that can't trigger Actions (issue comments, review submissions on draft PRs, label changes from bots, push to branches with no PR) are out of reach. v2 adds a webhook receiver for those.

## Sync direction and trigger model

One-way: **GitHub → Growth.** No write-back in v1.

Trigger events the v1 workflow handles:

| GitHub event | Growth call | Maps to |
|---|---|---|
| `pull_request` (opened, synchronize, closed) | `upsert-delivery-link` | `WorkItemDeliveryLink` (type=`pull_request`) |
| `check_run` (completed) | `upsert-check-run` | `CheckRunEvidence` for third-party CI |
| `workflow_run` (completed) | `upsert-check-run` | `CheckRunEvidence` for GitHub Actions CI |
| `deployment_status` | `upsert-deployment` | `Deployment` |
| `release` (published) | `upsert-release` | `Release` |

## Auth

- **Workspace-scoped Passport personal access token**, scope `mcp:use`. Existing flow.
- **One token per repo**, stored as `GROWTH_MCP_TOKEN` repo secret.
- **No GitHub App** in v1. A GitHub App becomes worthwhile only when (a) we need webhook delivery, or (b) we want to read across many repos without per-repo install. Defer.

## Mapping rules: how a PR finds its work item

Implemented conventions, in resolution order:

1. **Commit trailer.** `Growth-Work-Item: <work-item-id>` or `Growth-Change-Request: <change-request-id-or-CR-number>`.
2. **Branch reference.** `WI-<number>` or `CR-<number>` in the branch name.
3. **Explicit branch delivery link.** A work-item or change-request branch link previously recorded in Growth.

If no work item or change request is found, the workflow records an
unattributed event and reports the configured attribution check result.

Project ↔ repo binding needs a new field. Smallest change: add `github_repo` (e.g. `owner/repo`) to `projects`. Many repos per project is plausible later; defer to v2.

## Failure and drift handling

- **Workflow retries** handle transient HTTP errors (Action native).
- **Idempotent MCP tools** (`updateOrCreate` on `(work_item_delivery_link_id, provider, name)`) make replay safe.
- **No reconciliation job in v1.** If a PR is created before the workflow exists, it stays unrecorded. The trailer convention applies retroactively only via a manual `growth import` Action that walks open PRs — a v2 nice-to-have.
- **Conflict resolution:** GitHub is the source of truth for delivery/check/deployment/release records. Growth UI for those fields becomes read-only when `provider` is set (out of v1 scope, flagged for follow-up).

## Open questions called out, not resolved

- **Binding convention** — trailer vs. branch prefix vs. PR title. Recommend trailer; needs validation with one real repo.
- **PR reviews → which Growth concept?** GitHub PR review (approve / request-changes) overlaps `ReviewDecisionEvent`, `ChangeApprovalEvent`, and `ReviewFinding`. Probably maps to `ChangeApprovalEvent` when the PR is associated with a `ChangeRequest`, otherwise skipped. Decide on real examples.
- **Many repos per project?** Out of v1. If needed, a `project_repositories` join table.
- **Issue comments / discussions as citations?** Tempting (rationale lives in PR threads), but raises retention, privacy, and indexing questions. Defer.
- **Other providers** — Linear, Jira, GitLab. The Action-calls-MCP pattern generalises (each provider ships its own runner); the webhook pattern requires per-provider signature logic.

## Out of scope for this note

- Two-way sync.
- A first implementation. This note frames the slice; an implementation issue follows once binding convention is chosen.
