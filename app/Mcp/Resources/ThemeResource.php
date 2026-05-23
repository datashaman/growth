<?php

namespace App\Mcp\Resources;

use App\Mcp\Resources\Concerns\ReturnsStructuredJson;
use App\Models\Theme;
use App\Support\ThemePreviewSpecimen;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\MimeType;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Contracts\HasUriTemplate;
use Laravel\Mcp\Server\Resource;
use Laravel\Mcp\Support\UriTemplate;

#[Name('Theme')]
#[Description('JSON metadata for a Growth theme, including the compiled CSS resource URI.')]
#[MimeType('application/json')]
class ThemeResource extends Resource implements HasUriTemplate
{
    use ReturnsStructuredJson;

    public function uriTemplate(): UriTemplate
    {
        return new UriTemplate('growth://themes/{theme}');
    }

    public function handle(Request $request): Response
    {
        $id = $request->get('theme');
        $theme = Theme::find($id);

        if (! $theme) {
            return Response::error("Theme [{$id}] not found.");
        }

        return $this->json([
            'type' => 'theme',
            'id' => $theme->id,
            'project_id' => $theme->project_id,
            'name' => $theme->name,
            'slug' => $theme->slug,
            'description' => $theme->description,
            'design_notes' => $theme->design_notes,
            'css_tokens' => $theme->css_tokens ?? [],
            'css' => [
                'uri' => "growth://themes/{$theme->id}/css",
                'mime_type' => 'text/css',
            ],
            'preview_specimen' => ThemePreviewSpecimen::contract(),
            'is_default' => $theme->is_default,
            'updated_at' => $theme->updated_at?->toIso8601String(),
        ]);
    }
}
