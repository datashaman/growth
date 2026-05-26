<?php

namespace App\Mcp\Tools\Architecture;

use App\Models\CustomViewpoint;
use App\Models\DesignView;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('List architecture viewpoints available to a project, including Growth built-ins and project custom viewpoints. Built-ins are accepted by upsert-architecture-view without creating a custom viewpoint.')]
class ListArchitectureViewpoints extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'project_id' => 'required|string|owned_project',
            'q' => 'nullable|string|max:255',
        ]);

        $term = isset($data['q']) ? trim((string) $data['q']) : '';

        $builtIns = collect(DesignView::BUILTIN_VIEWPOINTS)
            ->filter(fn (string $name): bool => $term === '' || str_contains(strtolower($name), strtolower($term)))
            ->map(fn (string $name): array => [
                'id' => null,
                'name' => $name,
                'type' => 'built_in',
                'builtin' => true,
                'concerns' => [],
                'element_types' => [],
                'languages' => [],
                'source' => 'Growth built-in viewpoint vocabulary',
            ]);

        $query = CustomViewpoint::query()->where('project_id', $data['project_id']);
        if ($term !== '') {
            $query->where('name', 'like', '%'.$term.'%');
        }

        $custom = $query->orderBy('name')->get()->map(fn ($viewpoint): array => [
            'id' => $viewpoint->id,
            'name' => $viewpoint->name,
            'type' => 'custom',
            'builtin' => false,
            'concerns' => $viewpoint->concerns,
            'element_types' => $viewpoint->element_types,
            'languages' => $viewpoint->languages,
            'source' => $viewpoint->source,
        ]);

        return Response::structured([
            'built_in' => DesignView::BUILTIN_VIEWPOINTS,
            'results' => $builtIns->concat($custom)->values()->all(),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'project_id' => $schema->string()->description('Project ULID')->required(),
            'q' => $schema->string()->description('Substring match on viewpoint name'),
        ];
    }
}
