<?php

namespace App\Mcp\Tools\Plan;

use App\Models\Theme;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('List themes/design languages available for mockup generation and preview.')]
class ListThemes extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'project_id' => 'required|string|owned_project',
        ]);

        $themes = Theme::query()
            ->where('project_id', $data['project_id'])
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        return Response::structured([
            'project_id' => $data['project_id'],
            'default_theme_id' => $themes->firstWhere('is_default', true)?->id,
            'total' => $themes->count(),
            'themes' => $themes->map(fn (Theme $theme): array => $this->row($theme))->values()->all(),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'project_id' => $schema->string()->description('Project ULID')->required(),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'project_id' => $schema->string()->required(),
            'default_theme_id' => $schema->string(),
            'total' => $schema->integer()->required(),
            'themes' => $schema->array()->required(),
        ];
    }

    private function row(Theme $theme): array
    {
        return [
            'id' => $theme->id,
            'project_id' => $theme->project_id,
            'name' => $theme->name,
            'slug' => $theme->slug,
            'description' => $theme->description,
            'design_notes' => $theme->design_notes,
            'css_tokens' => $theme->css_tokens ?? [],
            'css_token_count' => count($theme->normalizedCssTokens()),
            'has_raw_css' => filled($theme->raw_css),
            'is_default' => $theme->is_default,
            'updated_at' => $theme->updated_at?->toIso8601String(),
        ];
    }
}
