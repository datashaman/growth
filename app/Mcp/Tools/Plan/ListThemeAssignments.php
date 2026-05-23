<?php

namespace App\Mcp\Tools\Plan;

use App\Models\ThemeAssignment;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('List scoped theme assignments for a project. These rules tell agents which project theme applies to a site section, vendor/profile/entity, or similar project scope.')]
class ListThemeAssignments extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'project_id' => 'required|string|owned_project',
            'scope_type' => 'nullable|string|max:255',
        ]);

        $assignments = ThemeAssignment::query()
            ->with('theme')
            ->where('project_id', $data['project_id'])
            ->when(isset($data['scope_type']), fn ($query) => $query->where('scope_type', $data['scope_type']))
            ->orderBy('scope_type')
            ->orderBy('scope_key')
            ->get();

        return Response::structured([
            'project_id' => $data['project_id'],
            'total' => $assignments->count(),
            'assignments' => $assignments->map(fn (ThemeAssignment $assignment): array => $this->row($assignment))->values()->all(),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'project_id' => $schema->string()->description('Project ULID')->required(),
            'scope_type' => $schema->string()->description('Optional assignment scope type filter, such as site_section or entity.'),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'project_id' => $schema->string()->required(),
            'total' => $schema->integer()->required(),
            'assignments' => $schema->array()->required(),
        ];
    }

    private function row(ThemeAssignment $assignment): array
    {
        return [
            'id' => $assignment->id,
            'project_id' => $assignment->project_id,
            'theme_id' => $assignment->theme_id,
            'theme_slug' => $assignment->theme?->slug,
            'theme_name' => $assignment->theme?->name,
            'scope_type' => $assignment->scope_type,
            'scope_key' => $assignment->scope_key,
            'label' => $assignment->label,
            'notes' => $assignment->notes,
            'updated_at' => $assignment->updated_at?->toIso8601String(),
        ];
    }
}
