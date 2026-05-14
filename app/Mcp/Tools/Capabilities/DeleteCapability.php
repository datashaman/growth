<?php

namespace App\Mcp\Tools\Capabilities;

use App\Models\Requirement;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Delete a capability. Child capabilities are detached from the deleted parent.')]
class DeleteCapability extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'id' => 'required|string|owned_requirement',
        ]);

        $capability = Requirement::findOrFail($data['id']);
        $children = $capability->children()->count();
        $capability->delete();

        return Response::structured([
            'id' => $data['id'],
            'deleted' => true,
            'children_detached' => $children,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->description('Capability ULID to delete')->required(),
        ];
    }
}
