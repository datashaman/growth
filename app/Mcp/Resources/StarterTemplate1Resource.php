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

#[Name('Starter Manifest — Rigor 1')]
#[Description('Apply-manifest-ready starter for a Rigor 1 project. Provides a project + plan (scope/approach) + one stakeholder, concern, capability, and architecture context view. Read this, replace the TODO placeholders, then call apply-manifest.')]
#[MimeType('application/json')]
#[Uri('growth://template/rigor-1')]
class StarterTemplate1Resource extends Resource
{
    public function __construct(private readonly StarterTemplates $templates) {}

    public function handle(Request $request): Response
    {
        return Response::json($this->templates->template(1));
    }
}
