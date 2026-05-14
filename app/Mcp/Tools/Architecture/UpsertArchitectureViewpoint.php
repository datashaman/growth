<?php

namespace App\Mcp\Tools\Architecture;

use App\Models\CustomViewpoint;
use App\Models\DesignView;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Create or update a project-scoped architecture viewpoint for concerns not covered by built-in viewpoints.')]
class UpsertArchitectureViewpoint extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'id' => 'nullable|string|owned_custom_viewpoint',
            'project_id' => 'required|string|owned_project',
            'name' => [
                'required', 'string', 'max:80',
                Rule::notIn(DesignView::BUILTIN_VIEWPOINTS),
            ],
            'concerns' => 'required|array|min:1',
            'concerns.*' => 'string|min:2',
            'element_types' => 'required|array|min:1',
            'element_types.*' => 'string|min:2',
            'languages' => 'required|array|min:1',
            'languages.*' => 'string|min:2',
            'source' => 'nullable|string|max:255',
        ]);

        $id = $data['id'] ?? null;
        unset($data['id']);

        $viewpoint = $id
            ? tap(CustomViewpoint::findOrFail($id))->update($data)
            : CustomViewpoint::updateOrCreate(
                ['project_id' => $data['project_id'], 'name' => $data['name']],
                $data,
            );

        return Response::structured([
            'id' => $viewpoint->id,
            'name' => $viewpoint->name,
            'created' => $viewpoint->wasRecentlyCreated,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->description('Existing custom viewpoint ULID. Omit to create or upsert by project/name.'),
            'project_id' => $schema->string()->description('Project ULID')->required(),
            'name' => $schema->string()->description('Viewpoint name, unique per project and distinct from built-ins')->required(),
            'concerns' => $schema->array()->description('Concern categories this viewpoint frames')->required(),
            'element_types' => $schema->array()->description('Element types introduced by this viewpoint')->required(),
            'languages' => $schema->array()->description('Design notations or languages this viewpoint can use')->required(),
            'source' => $schema->string()->description('Optional authorship or source note'),
        ];
    }
}
