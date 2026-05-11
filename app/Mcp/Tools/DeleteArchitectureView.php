<?php

namespace App\Mcp\Tools;

use App\Models\DesignView;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Delete an architecture view, its elements, and its concern links.')]
class DeleteArchitectureView extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate(['id' => 'required|string|owned_design_view']);

        $view = DesignView::findOrFail($data['id']);
        $elements = $view->elements()->count();
        $concerns = $view->concerns()->count();
        $view->delete();

        return Response::structured([
            'id' => $data['id'],
            'deleted' => true,
            'elements_deleted' => $elements,
            'concerns_unlinked' => $concerns,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return ['id' => $schema->string()->description('Architecture view ULID')->required()];
    }
}
