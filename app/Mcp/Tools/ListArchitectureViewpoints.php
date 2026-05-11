<?php

namespace App\Mcp\Tools;

use App\Models\CustomViewpoint;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('List custom architecture viewpoints for a project.')]
class ListArchitectureViewpoints extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'project_id' => 'required|string|owned_project',
            'q' => 'nullable|string|max:255',
        ]);

        $query = CustomViewpoint::query()->where('project_id', $data['project_id']);
        if (isset($data['q'])) {
            $query->where('name', 'like', '%'.$data['q'].'%');
        }

        return Response::structured([
            'results' => $query->orderBy('name')->get()->map(fn ($viewpoint) => [
                'id' => $viewpoint->id,
                'name' => $viewpoint->name,
                'concerns' => $viewpoint->concerns,
                'element_types' => $viewpoint->element_types,
                'languages' => $viewpoint->languages,
                'source' => $viewpoint->source,
            ])->all(),
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
