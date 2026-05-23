<?php

namespace App\Mcp\Resources;

use App\Mcp\Resources\Concerns\InspectsMockups;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\MimeType;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Contracts\HasUriTemplate;
use Laravel\Mcp\Server\Resource;
use Laravel\Mcp\Support\UriTemplate;

#[Name('Mockup Revision')]
#[Description('Browser preview for one spec mockup revision, including preview URL, theme context, visible text, warnings for visible Growth/internal metadata, and a screenshot resource URI. Pass ?theme=none or ?theme={slug} to override assigned theme.')]
#[MimeType('application/json')]
class MockupRevisionResource extends Resource implements HasUriTemplate
{
    use InspectsMockups;

    public function uriTemplate(): UriTemplate
    {
        return new UriTemplate('growth://mockups/{mockup}/{revision}');
    }

    public function handle(Request $request): Response
    {
        return $this->inspectionResponse($request, (string) $request->get('revision'));
    }
}
