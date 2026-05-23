<?php

namespace App\Mcp\Resources;

use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\MimeType;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Support\UriTemplate;

#[Name('Themed Mockup Revision Screenshot')]
#[Description('PNG screenshot pixels for a specific spec mockup revision with an explicit theme query parameter.')]
#[MimeType('image/png')]
class ThemedMockupRevisionScreenshotResource extends MockupRevisionScreenshotResource
{
    public function uriTemplate(): UriTemplate
    {
        return new UriTemplate('growth://mockups/{mockup}/{revision}/screenshot?theme={theme}');
    }
}
