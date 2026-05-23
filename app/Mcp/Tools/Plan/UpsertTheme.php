<?php

namespace App\Mcp\Tools\Plan;

use App\Models\Theme;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[IsDestructive(false)]
#[Description('Create or update a theme/design language for mockup generation and preview. This is the primary theme management surface; the web app only displays and selects themes.')]
class UpsertTheme extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'id' => 'nullable|string|owned_theme',
            'project_id' => 'required_without:id|string|owned_project',
            'name' => 'required_without:id|string|max:255',
            'slug' => [
                'nullable',
                'string',
                'max:255',
                'regex:/^[a-z0-9][a-z0-9_-]*$/',
                Rule::unique('themes', 'slug')
                    ->where('project_id', $request->get('project_id') ?? Theme::whereKey($request->get('id'))->value('project_id'))
                    ->ignore($request->get('id')),
            ],
            'description' => 'nullable|string',
            'design_notes' => 'nullable|string',
            'css_tokens' => 'nullable|array',
            'raw_css' => 'nullable|string',
            'is_default' => 'nullable|boolean',
        ]);

        $theme = isset($data['id'])
            ? Theme::findOrFail($data['id'])
            : new Theme(['project_id' => $data['project_id']]);

        $projectId = $theme->project_id ?? $data['project_id'];
        $tokens = array_key_exists('css_tokens', $data) ? $data['css_tokens'] : $theme->css_tokens;
        $rawCss = array_key_exists('raw_css', $data) ? $data['raw_css'] : $theme->raw_css;
        Theme::validateSelfContainedCss($tokens, $rawCss);

        foreach (['name', 'slug', 'description', 'design_notes', 'css_tokens', 'raw_css'] as $field) {
            if (array_key_exists($field, $data)) {
                $theme->{$field} = $data[$field];
            }
        }

        if (! $theme->exists && blank($theme->slug)) {
            $theme->slug = Str::slug($theme->name);
        }

        if (blank($theme->slug)) {
            $theme->slug = 'theme-'.Str::lower((string) Str::ulid());
        }

        $slugTaken = Theme::query()
            ->where('project_id', $projectId)
            ->where('slug', $theme->slug)
            ->when($theme->exists, fn ($query) => $query->whereKeyNot($theme->getKey()))
            ->exists();

        if ($slugTaken) {
            throw ValidationException::withMessages([
                'slug' => 'The slug has already been taken for this project.',
            ]);
        }

        $theme->project_id = $projectId;
        $theme->save();

        if (($data['is_default'] ?? false) === true) {
            $theme->markDefault();
        } elseif (array_key_exists('is_default', $data) && $data['is_default'] === false && $theme->is_default) {
            $theme->clearDefault();
        }

        return Response::structured([
            'id' => $theme->id,
            'project_id' => $theme->project_id,
            'name' => $theme->name,
            'slug' => $theme->slug,
            'description' => $theme->description,
            'design_notes' => $theme->design_notes,
            'css_tokens' => $theme->css_tokens ?? [],
            'raw_css' => $theme->raw_css,
            'is_default' => $theme->is_default,
            'created' => $theme->wasRecentlyCreated,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->description('Existing theme ULID. Omit to create.'),
            'project_id' => $schema->string()->description('Project ULID. Required when creating.'),
            'name' => $schema->string()->description('Human-readable theme name. Required when creating.'),
            'slug' => $schema->string()->description('Stable per-project key used by mockup preview URLs. Lowercase letters, digits, underscores, and hyphens. Omit to derive from name on create.'),
            'description' => $schema->string()->description('Short summary of the theme.'),
            'design_notes' => $schema->string()->description('Design-language prose agents should follow when generating mockups.'),
            'css_tokens' => $schema->object()->description('CSS token map. Keys become CSS custom properties; values must be self-contained CSS values.'),
            'raw_css' => $schema->string()->description('Optional self-contained CSS. Do not use @import or remote url(...) assets.'),
            'is_default' => $schema->boolean()->description('When true, makes this the project default theme and clears other defaults.'),
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
            'raw_css' => $schema->string(),
            'is_default' => $schema->boolean()->required(),
            'created' => $schema->boolean()->required(),
        ];
    }
}
