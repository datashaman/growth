<?php

namespace App\Mcp\Resources;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\MimeType;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Uri;
use Laravel\Mcp\Server\Resource;

#[Name('Playbook')]
#[Description('Internal AI-aligned project playbook used by Growth MCP agents.')]
#[MimeType('text/markdown')]
#[Uri('growth://playbook')]
class PlaybookResource extends Resource
{
    public function handle(Request $request): Response
    {
        return Response::text(<<<'MD'
# Growth Playbook

The MCP layer treats a project definition as the control plane for AI-assisted delivery.

## Bootstrapping a new project

Prefer the manifest workflow over per-entity upserts when starting from scratch:

1. Read a starter at `growth://template/rigor-{1,2,3,4}` matching the target rigor level.
2. Fill in TODO placeholders (project name/description, scope, requirement text, acceptance criteria, verification scope).
3. Call `apply-manifest` with the filled-in manifest. One call creates the project plus its stakeholders, concerns, requirements, architecture view, plan, and verification plan/case.
4. For L3+, follow up with `baseline-plan` and `upsert-review` — baselines and reviews are events, not manifest content.

For existing projects, `export-manifest` round-trips structure to YAML/JSON for version-controlled edits; re-applying an unchanged manifest is a no-op (`merge` mode).

## Adopting and backfilling an existing repository

When a project is adopted rather than greenfield — `adopt-project` has bound the repo, stamped `adopted_at`, and set an `adoption`-kind plan baseline — the project definition must be reconstructed *from the code*. The backfill keeps a clear seam between **recovered fact** (derivable from the code without judgement) and **assumed intent** (what the project is *for*, which the code cannot tell you). Citation to the adoption `Source` marks that seam: a cited artifact is recovered fact; an uncited one is assumed intent.

1. **Reconstruct the extensional layer.** Walk the repository and build a Growth manifest capturing what the code *is*: modules and models as architecture `entity` elements, module-dependency edges as `relationship` elements, and routes — organised into architecture viewpoints and views. This is recovered fact; the repo walk is an agent task, not deterministic code.
2. **Apply via `apply-manifest` in `merge` mode**, passing `dry_run: true` first so the human previews the reconstruction before anything is written. Re-run without `dry_run` to commit.
3. **Record provenance.** Create one adoption `Source` with `upsert-source` using `kind: source`, `uri` set to the repository, and `external_ref` set to the adoption baseline commit. Cite each reconstructed architecture **view** to that Source with `cite-artifact` using the `design_view` citable type. The presence of a repo citation marks an artifact as recovered fact; its absence marks assumed intent.
4. **Supply intent — human-led.** Requirements, concerns, and stakeholders are assumed intent: the agent must not invent them. Gather them from the human, apply them as a further `apply-manifest` `merge`, and do **not** cite them to the adoption Source — leaving them uncited is what records them as assumed intent.
5. **Route through a review.** Open a `technical_review` with `upsert-review` whose `objective` names the project, repository, and adoption baseline commit, and whose `entry_criteria` reference the adoption `Source` and baseline. Run it: `start-review`, then `upsert-review-finding`, then `close-review`. Findings against repo-cited artifacts mean recovered fact is inaccurate; findings against uncited requirements/concerns mean assumed intent is wrong. The review `decision` is the recorded sign-off.

## Loop

1. Capture intent and sources.
2. Convert intent into requirements with acceptance checks.
3. Shape architecture around concerns and constraints.
4. Plan work items that cover requirements.
5. Attach implementation and check evidence.
6. Review decisions, changes, and release readiness before shipping.

## Post-baseline change management

After a plan baseline exists, treat changes to scope, requirements, design, verification, risk posture, or delivery plan as controlled changes. Use an anomaly for an observed defect, failure, drift, or unexpected condition that still needs investigation; use a change request when the team is proposing or recording an intentional alteration to a controlled artifact. If an anomaly leads to an intentional alteration, link the follow-up work through a change request rather than hiding the decision in the anomaly record.

Every change request needs impacted artifacts. Record the concrete requirements, work items, architecture views/elements, risks, reviews, plans, verification artifacts, or other project artifacts touched by the change. A change request with no impacts leaves the `change_control` readiness gate unhealthy because reviewers cannot see what is being changed, referenced, or retired.

Decision rationale is part of the control record. Approve, reject, or defer a change with the transition tools and include the reason that made the decision acceptable. The approve-to-implement flow is: create the proposed change request with impacts, submit it for review, approve/reject/defer it with rationale, implement only approved changes, attach delivery evidence, then mark the approved change request implemented.

## Quality Rules

- Requirements are clear, singular, testable, and grounded in sources.
- Acceptance checks are concrete pass/fail statements.
- Architecture views address recorded concerns.
- Work items link back to the requirements they deliver.
- Evidence links connect implementation, checks, releases, and deployments.
- Readiness gates report gaps without relying on proprietary source text.
MD);
    }
}
