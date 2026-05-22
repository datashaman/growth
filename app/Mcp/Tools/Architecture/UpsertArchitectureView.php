<?php

namespace App\Mcp\Tools\Architecture;

use App\Models\CustomViewpoint;
use App\Models\DesignView;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[IsDestructive(false)]
#[Description('Create or update an architecture view and optionally sync the concerns it addresses. Before generating architecture artifacts, inspect stakeholders, concerns, requirements, existing views/elements, and source citations so the view captures useful agent-facing design context.')]
class UpsertArchitectureView extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'id' => 'nullable|string|owned_design_view',
            'project_id' => 'required|string|owned_project',
            'viewpoint' => [
                'required', 'string',
                function ($attr, $value, $fail) use ($request) {
                    $projectId = $request->get('project_id');
                    if (in_array($value, DesignView::BUILTIN_VIEWPOINTS, true)) {
                        return;
                    }
                    if (! CustomViewpoint::where('project_id', $projectId)->where('name', $value)->exists()) {
                        $fail("Viewpoint [{$value}] is not built in and is not a custom viewpoint for this project.");
                    }
                },
            ],
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'addresses_concern_ids' => 'nullable|array',
            'addresses_concern_ids.*' => 'string|owned_concern',
        ]);

        $id = $data['id'] ?? null;
        $concernIds = $data['addresses_concern_ids'] ?? null;
        unset($data['id'], $data['addresses_concern_ids']);

        $view = DB::transaction(function () use ($id, $data, $concernIds) {
            $view = $id
                ? tap(DesignView::findOrFail($id))->update($data)
                : DesignView::create($data);

            if (is_array($concernIds)) {
                $view->concerns()->sync($concernIds);
            }

            return $view;
        });

        return Response::structured([
            'id' => $view->id,
            'viewpoint' => $view->viewpoint,
            'name' => $view->name,
            'created' => $view->wasRecentlyCreated,
            'concerns_attached' => $view->concerns()->count(),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->description('Existing architecture view ULID. Omit to create.'),
            'project_id' => $schema->string()->description('Project ULID')->required(),
            'viewpoint' => $schema->string()->description('Built-in or custom viewpoint name')->required(),
            'name' => $schema->string()->description('Human label for this view')->required(),
            'description' => $schema->string()->description('Agent-facing design context for this view, grounded in relevant concerns, requirements, sources, or existing architecture'),
            'addresses_concern_ids' => $schema->array()->description('Concern ULIDs this view addresses'),
        ];
    }
}
