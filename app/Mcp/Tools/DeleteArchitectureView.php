<?php

namespace App\Mcp\Tools;

use App\Models\DesignView;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Delete an architecture view. Cascades: every element of the view is deleted; concern links are removed. Requires confirm_name to match the view name exactly.')]
class DeleteArchitectureView extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'id' => 'required|string|owned_design_view',
            'confirm_name' => 'required|string',
        ]);

        $view = DesignView::findOrFail($data['id']);

        if ($data['confirm_name'] !== $view->name) {
            return new ResponseFactory(Response::error(
                "Confirmation mismatch. Pass the view's exact name in `confirm_name` to delete it. View is named [{$view->name}]."
            ));
        }

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
        return [
            'id' => $schema->string()->description('Architecture view ULID')->required(),
            'confirm_name' => $schema->string()
                ->description('Must match the view name exactly to guard against accidental deletion')
                ->required(),
        ];
    }
}
