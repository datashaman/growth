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

#[Description('Delete a custom architecture viewpoint. Refuses while any architecture view still uses it.')]
class DeleteArchitectureViewpoint extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'id' => 'required|string|owned_custom_viewpoint',
        ]);

        $vp = CustomViewpoint::findOrFail($data['id']);

        $inUse = DesignView::where('project_id', $vp->project_id)
            ->where('viewpoint', $vp->name)
            ->count();

        if ($inUse > 0) {
            return new ResponseFactory(Response::error(
                "Custom viewpoint [{$vp->name}] is still used by {$inUse} architecture view(s). Update or delete those views before removing the viewpoint."
            ));
        }

        $vp->delete();

        return Response::structured([
            'id' => $data['id'],
            'deleted' => true,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()
                ->description('Custom viewpoint ULID to delete')
                ->required(),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->required(),
            'deleted' => $schema->boolean()->required(),
        ];
    }
}
