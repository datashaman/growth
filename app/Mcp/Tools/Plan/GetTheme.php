<?php

namespace App\Mcp\Tools\Plan;

use App\Models\Theme;
use App\Support\ThemePreviewSpecimen;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('Resolve a theme/design language and return its metadata and CSS resource URIs.')]
class GetTheme extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'id' => 'required|string|owned_theme',
        ]);

        $theme = Theme::findOrFail($data['id']);

        return Response::structured([
            'id' => $theme->id,
            'project_id' => $theme->project_id,
            'name' => $theme->name,
            'slug' => $theme->slug,
            'description' => $theme->description,
            'design_notes' => $theme->design_notes,
            'css_tokens' => $theme->css_tokens ?? [],
            'resources' => [
                'theme_uri' => "growth://themes/{$theme->id}",
                'css_uri' => "growth://themes/{$theme->id}/css",
                'guidance' => 'Read theme_uri for JSON metadata. Read css_uri for compiled self-contained CSS.',
            ],
            'preview_specimen' => ThemePreviewSpecimen::contract(),
            'is_default' => $theme->is_default,
            'updated_at' => $theme->updated_at?->toIso8601String(),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->description('Theme ULID')->required(),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->required(),
            'project_id' => $schema->string()->required(),
            'name' => $schema->string()->required(),
            'slug' => $schema->string()->required(),
            'description' => $schema->string(),
            'design_notes' => $schema->string(),
            'css_tokens' => $schema->object(),
            'resources' => $schema->object()->description('Resource URIs for theme metadata and compiled self-contained CSS')->required(),
            'preview_specimen' => $schema->object()->required(),
            'is_default' => $schema->boolean()->required(),
            'updated_at' => $schema->string(),
        ];
    }
}
