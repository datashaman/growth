<?php

namespace App\Mcp\Resources;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\MimeType;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Uri;
use Laravel\Mcp\Server\Resource;

#[Name('Rigor Levels')]
#[Description('Rule activation table for project rigor levels 1–4. Linters consult this matrix when deciding which checks to enforce.')]
#[MimeType('text/markdown')]
#[Uri('growth://rigor-levels')]
class RigorLevelsResource extends Resource
{
    public function handle(Request $request): Response
    {
        return Response::text(<<<'MD'
# Rigor Levels

Project rigor (stored as `integrity_level`, exposed on some tools as `rigor_level`) selects the strictness of lint and readiness checks. Each level inherits the rules of the levels below it.

## Activation Table

| Level | Rules activated at this level |
|-------|-------------------------------|
| 1     | Project Management Plan must exist with `scope_summary` and `approach`. |
| 2     | ≥ 1 milestone defined. <br> ≥ 1 work item defined. |
| 3     | Every work item has a responsible role (RACI). <br> Project defines ≥ 1 role. <br> Plan baseline exists. <br> Reviews / audits are recorded. <br> Acceptance criteria required for **all** requirements (not just high-priority). |
| 4     | Ceiling. No rules unique to L4 today — reserved for future safety-critical extensions. |

## Sources of truth

- `App\Growth\Lint\PmpLinter` — milestone, work item, RACI, role rules.
- `App\Growth\Lint\BaselineLinter` — plan baseline rule.
- `App\Growth\Lint\ReviewLinter` — review/audit recording rule.
- `App\Growth\Lint\RequirementLinter` — acceptance criteria escalation.

## Defaults

- New projects default to **rigor level 2** unless explicitly set.
- Most lint rules apply at every level; only the rules above are gated on rigor.
MD);
    }
}
