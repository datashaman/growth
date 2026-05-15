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

## Loop

1. Capture intent and sources.
2. Convert intent into requirements with acceptance checks.
3. Shape architecture around concerns and constraints.
4. Plan work items that cover requirements.
5. Attach implementation and check evidence.
6. Review decisions, changes, and release readiness before shipping.

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
