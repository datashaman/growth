<?php

namespace App\Mcp\Tools\Design;

use App\Models\CustomViewpoint;
use App\Models\DesignView;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Define a project-scoped custom design viewpoint (architecture coverage rules). Use when the 12 built-in viewpoints (context, composition, logical, dependency, information, patterns, interface, structure, interaction, state_dynamics, algorithm, resource) do not cover a needed concern. Upserts by (project_id, name); names must not collide with built-ins.')]
class DefineCustomViewpoint extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'project_id' => 'required|string|owned_project',
            'name' => 'required|string|max:100|not_in:'.implode(',', DesignView::BUILTIN_VIEWPOINTS),
            'concerns' => 'required|array|min:1',
            'concerns.*' => 'string|max:255',
            'element_types' => 'required|array|min:1',
            'element_types.*' => 'string|max:100',
            'languages' => 'required|array|min:1',
            'languages.*' => 'string|max:100',
            'source' => 'nullable|string|max:255',
        ]);

        $viewpoint = CustomViewpoint::updateOrCreate(
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
            'project_id' => $schema->string()
                ->description('Project ULID — viewpoints are project-scoped')
                ->required(),
            'name' => $schema->string()
                ->description('Viewpoint name — must not collide with a built-in')
                ->required(),
            'concerns' => $schema->array()
                ->description('Concern types this viewpoint frames (required viewpoint field)'),
            'element_types' => $schema->array()
                ->description('Element type names introduced by this viewpoint (required viewpoint field)'),
            'languages' => $schema->array()
                ->description('Design languages this viewpoint can use (required viewpoint field)'),
            'source' => $schema->string()
                ->description('Citation or authorship (rule viewpoint source)'),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->required(),
            'name' => $schema->string()->required(),
            'created' => $schema->boolean()->required(),
        ];
    }
}
