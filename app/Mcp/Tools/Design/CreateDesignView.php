<?php

namespace App\Mcp\Tools\Design;

use App\Models\CustomViewpoint;
use App\Models\DesignView;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Create a design view (architecture coverage rules) instantiating a viewpoint. Viewpoint may be one of the 12 built-ins or a project-scoped custom viewpoint defined via define-custom-viewpoint. Optionally links to concerns the view addresses.')]
class CreateDesignView extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'project_id' => 'required|string|owned_project',
            'viewpoint' => [
                'required', 'string',
                function ($attr, $value, $fail) use ($request) {
                    $projectId = $request->get('project_id');
                    if (in_array($value, DesignView::BUILTIN_VIEWPOINTS, true)) {
                        return;
                    }
                    $exists = CustomViewpoint::where('project_id', $projectId)
                        ->where('name', $value)
                        ->exists();
                    if (! $exists) {
                        $fail("Viewpoint [{$value}] is neither a architecture built-in nor a custom viewpoint defined in this project.");
                    }
                },
            ],
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'addresses_concern_ids' => 'nullable|array',
            'addresses_concern_ids.*' => 'string|owned_concern',
        ]);

        $concernIds = $data['addresses_concern_ids'] ?? [];
        unset($data['addresses_concern_ids']);

        $view = DB::transaction(function () use ($data, $concernIds) {
            $v = DesignView::create($data);
            if ($concernIds !== []) {
                $v->concerns()->attach($concernIds);
            }

            return $v;
        });

        return Response::structured([
            'id' => $view->id,
            'viewpoint' => $view->viewpoint,
            'name' => $view->name,
            'concerns_attached' => count($concernIds),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'project_id' => $schema->string()
                ->description('Project ULID')
                ->required(),
            'viewpoint' => $schema->string()
                ->description('Either one of the 12 architecture built-in viewpoints ('.implode(', ', DesignView::BUILTIN_VIEWPOINTS).') or the name of a custom viewpoint already defined in this project via define-custom-viewpoint')
                ->required(),
            'name' => $schema->string()
                ->description('Human label for this view (e.g. "Payment service logical view")')
                ->required(),
            'description' => $schema->string()
                ->description('Optional view description'),
            'addresses_concern_ids' => $schema->array()
                ->description('Concern ULIDs this view addresses (coverage rule)'),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->required(),
            'viewpoint' => $schema->string()->required(),
            'name' => $schema->string()->required(),
            'concerns_attached' => $schema->integer()->required(),
        ];
    }
}
