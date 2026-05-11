<?php

namespace App\Mcp\Tools\Design;

use App\Models\DesignView;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Attach concerns to an existing design view (architecture coverage rules). Idempotent — calling twice with the same concerns leaves the view in the same state. Use this when concerns surface after the view was created.')]
class LinkConcernsToView extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'design_view_id' => 'required|string|owned_design_view',
            'concern_ids' => 'required|array|min:1',
            'concern_ids.*' => 'string|owned_concern',
        ]);

        $view = DesignView::findOrFail($data['design_view_id']);
        $before = $view->concerns()->count();
        $view->concerns()->syncWithoutDetaching($data['concern_ids']);
        $after = $view->concerns()->count();

        return Response::structured([
            'design_view_id' => $view->id,
            'attempted' => count($data['concern_ids']),
            'newly_attached' => $after - $before,
            'total_attached' => $after,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'design_view_id' => $schema->string()
                ->description('View ULID')
                ->required(),
            'concern_ids' => $schema->array()
                ->description('Concern ULIDs to attach (duplicates silently ignored)'),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'design_view_id' => $schema->string()->required(),
            'attempted' => $schema->integer()->required(),
            'newly_attached' => $schema->integer()->required(),
            'total_attached' => $schema->integer()->required(),
        ];
    }
}
