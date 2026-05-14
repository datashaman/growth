<?php

namespace App\Mcp\Resources;

use App\Growth\Manifest\StarterTemplates;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\MimeType;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Uri;
use Laravel\Mcp\Server\Resource;

#[Name('Starter Manifest — Rigor 3')]
#[Description('Apply-manifest-ready starter for a Rigor 3 project. Adds a role + work-item RACI to the Rigor 2 scaffold. After applying, follow up with `baseline-plan` and `upsert-review` to satisfy the L3 baseline and review-readiness rules (events live outside the manifest by design).')]
#[MimeType('application/json')]
#[Uri('growth://template/rigor-3')]
class StarterTemplate3Resource extends Resource
{
    public function __construct(private readonly StarterTemplates $templates) {}

    public function handle(Request $request): Response
    {
        return Response::json($this->templates->template(3));
    }
}
