<?php

namespace App\Mcp\Tools\Design;

use App\Models\DesignView;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Delete a design view (architecture coverage rules). Its design elements cascade-delete; concern↔view links are removed via the pivot. Concerns themselves remain — they may need to be linked to a different view to keep rule completeness.')]
class DeleteDesignView extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'id' => 'required|string|owned_design_view',
        ]);

        $view = DesignView::findOrFail($data['id']);
        $elementsDeleted = $view->elements()->count();
        $concernsUnlinked = $view->concerns()->count();
        $view->delete();

        return Response::structured([
            'id' => $data['id'],
            'deleted' => true,
            'elements_deleted' => $elementsDeleted,
            'concerns_unlinked' => $concernsUnlinked,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()
                ->description('Design view ULID to delete')
                ->required(),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->required(),
            'deleted' => $schema->boolean()->required(),
            'elements_deleted' => $schema->integer()->required(),
            'concerns_unlinked' => $schema->integer()->required(),
        ];
    }
}
