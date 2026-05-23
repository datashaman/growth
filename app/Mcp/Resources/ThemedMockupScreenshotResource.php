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

#[Name('Themed Mockup Screenshot')]
#[Description('PNG screenshot for a browser preview of a spec mockup revision with an explicit theme query, such as ?theme=none or ?theme={slug}.')]
#[MimeType('image/png')]
class ThemedMockupScreenshotResource extends Resource implements HasUriTemplate
{
    use InspectsMockups;

    public function uriTemplate(): UriTemplate
    {
        return new UriTemplate('growth://mockups/{mockup}/{revision}/screenshot?theme={theme}');
    }

    public function handle(Request $request): Response
    {
        return $this->screenshotResponse($request);
    }
}
