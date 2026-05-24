<?php

namespace App\Mcp\Tools\Plan;

use App\Models\Mockup;
use App\Models\Theme;
use App\Models\ThemeAssignment;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[IsDestructive(false)]
#[Description('Create or update a scoped theme assignment for a project scope such as a site section, vendor/profile/entity, mockup, or other named project area. For scope_type=mockup, scope_key must be the mockup ULID used by growth://mockups/{mockup}.')]
class UpsertThemeAssignment extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'id' => 'nullable|string|owned_theme_assignment',
            'project_id' => 'required_without:id|string|owned_project',
            'theme_id' => 'required|string|owned_theme',
            'scope_type' => [
                'required_without:id',
                'string',
                'max:255',
                'regex:/^[a-z0-9][a-z0-9_-]*$/',
            ],
            'scope_key' => [
                'required_without:id',
                'string',
                'max:255',
                'regex:/^[A-Za-z0-9][A-Za-z0-9_.:\/-]*$/',
            ],
            'label' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ]);

        $assignment = isset($data['id'])
            ? ThemeAssignment::findOrFail($data['id'])
            : new ThemeAssignment([
                'project_id' => $data['project_id'],
                'scope_type' => $data['scope_type'],
                'scope_key' => $data['scope_key'],
            ]);

        $projectId = $assignment->project_id ?? $data['project_id'];
        $theme = Theme::findOrFail($data['theme_id']);

        if ($theme->project_id !== $projectId) {
            throw ValidationException::withMessages([
                'theme_id' => 'The selected theme must belong to the assignment project.',
            ]);
        }

        foreach (['scope_type', 'scope_key', 'label', 'notes'] as $field) {
            if (array_key_exists($field, $data)) {
                $assignment->{$field} = $data[$field];
            }
        }

        $this->validateMockupScope($assignment, $projectId);

        validator($assignment->getAttributes(), [
            'scope_type' => [
                'required',
                'string',
                'max:255',
                Rule::unique('theme_assignments', 'scope_type')
                    ->where('project_id', $projectId)
                    ->where('scope_key', $assignment->scope_key)
                    ->ignore($assignment->id),
            ],
        ])->validate();

        $assignment->project_id = $projectId;
        $assignment->theme_id = $theme->id;
        $assignment->save();
        $assignment->load('theme');

        return Response::structured([
            'id' => $assignment->id,
            'project_id' => $assignment->project_id,
            'theme_id' => $assignment->theme_id,
            'theme_slug' => $assignment->theme->slug,
            'scope_type' => $assignment->scope_type,
            'scope_key' => $assignment->scope_key,
            'label' => $assignment->label,
            'notes' => $assignment->notes,
            'target' => $this->target($assignment),
            'created' => $assignment->wasRecentlyCreated,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->description('Existing theme assignment ULID. Omit to create.'),
            'project_id' => $schema->string()->description('Project ULID. Required when creating.'),
            'theme_id' => $schema->string()->description('Theme ULID to apply to the scope. Must belong to the assignment project.')->required(),
            'scope_type' => $schema->string()->description('Assignment scope type, such as site_section, entity, vendor, profile, route, or mockup. Required when creating.'),
            'scope_key' => $schema->string()->description('Stable key within the scope type. For scope_type=mockup, use the mockup ULID from growth://mockups/{mockup} or /mockups/{mockup}. Required when creating.'),
            'label' => $schema->string()->description('Optional human-readable scope label.'),
            'notes' => $schema->string()->description('Optional guidance explaining when and how to apply the theme.'),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->required(),
            'project_id' => $schema->string()->required(),
            'theme_id' => $schema->string()->required(),
            'theme_slug' => $schema->string()->required(),
            'scope_type' => $schema->string()->required(),
            'scope_key' => $schema->string()->required(),
            'label' => $schema->string(),
            'notes' => $schema->string(),
            'target' => $schema->object(),
            'created' => $schema->boolean()->required(),
        ];
    }

    private function validateMockupScope(ThemeAssignment $assignment, string $projectId): void
    {
        if ($assignment->scope_type !== 'mockup') {
            return;
        }

        $mockup = Mockup::with('owner')->find($assignment->scope_key);

        if (! $mockup) {
            throw ValidationException::withMessages([
                'scope_key' => 'The mockup scope key must be an existing mockup ULID.',
            ]);
        }

        if ((string) $mockup->owner?->project_id !== $projectId) {
            throw ValidationException::withMessages([
                'scope_key' => 'The mockup scope key must be an existing mockup ULID in the assignment project.',
            ]);
        }
    }

    /**
     * @return array<string,string>|null
     */
    private function target(ThemeAssignment $assignment): ?array
    {
        if ($assignment->scope_type !== 'mockup') {
            return null;
        }

        $mockup = Mockup::find($assignment->scope_key);

        if (! $mockup) {
            return null;
        }

        return [
            'mockup_id' => $mockup->id,
            'mockup_uri' => "growth://mockups/{$mockup->id}",
            'preview_url' => route('mockups.show', $mockup),
        ];
    }
}
