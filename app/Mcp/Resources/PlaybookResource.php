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

## Loop

1. Capture intent and sources.
2. Convert intent into capabilities with acceptance checks.
3. Shape architecture around concerns and constraints.
4. Plan work items that cover capabilities.
5. Attach implementation and check evidence.
6. Review decisions, changes, and release readiness before shipping.

## Quality Rules

- Capabilities are clear, singular, testable, and grounded in sources.
- Acceptance checks are concrete pass/fail statements.
- Architecture views address recorded concerns.
- Work items link back to the capabilities they deliver.
- Evidence links connect implementation, checks, releases, and deployments.
- Readiness gates report gaps without relying on proprietary source text.
MD);
    }
}
