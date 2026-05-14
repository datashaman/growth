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

#[Name('Starter Manifest — Rigor 4')]
#[Description('Apply-manifest-ready starter for a Rigor 4 project. Same structural rules as Rigor 3 (L4 is the ceiling); reserved for future safety-critical extensions. After applying, follow up with `baseline-plan` and `upsert-review`.')]
#[MimeType('application/json')]
#[Uri('growth://template/rigor-4')]
class StarterTemplate4Resource extends Resource
{
    public function __construct(private readonly StarterTemplates $templates) {}

    public function handle(Request $request): Response
    {
        return Response::json($this->templates->template(4));
    }
}
