<?php

namespace App\Mcp\Resources;

use App\Models\Theme;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\MimeType;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Contracts\HasUriTemplate;
use Laravel\Mcp\Server\Resource;
use Laravel\Mcp\Support\UriTemplate;

#[Name('Theme CSS')]
#[Description('Compiled self-contained CSS for a Growth theme, including normalized CSS tokens and raw CSS.')]
#[MimeType('text/css')]
class ThemeCssResource extends Resource implements HasUriTemplate
{
    public function uriTemplate(): UriTemplate
    {
        return new UriTemplate('growth://themes/{theme}/css');
    }

    public function handle(Request $request): Response
    {
        $id = $request->get('theme');
        $theme = Theme::find($id);

        if (! $theme) {
            return Response::error("Theme [{$id}] not found.");
        }

        return Response::text($theme->cssForInjection());
    }
}
