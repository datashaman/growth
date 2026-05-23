<?php

namespace App\Mcp\Resources;

use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\MimeType;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Support\UriTemplate;

#[Name('Themed Mockup Revision Preview')]
#[Description('Theme-aware preview HTML for a specific spec mockup revision with an explicit theme query parameter.')]
#[MimeType('text/html')]
class ThemedMockupRevisionPreviewResource extends MockupRevisionPreviewResource
{
    public function uriTemplate(): UriTemplate
    {
        return new UriTemplate('growth://mockups/{mockup}/{revision}/preview?theme={theme}');
    }
}
