<?php

namespace App\Mcp\Tools\Requirements;

use App\Models\Requirement;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[IsDestructive]
#[Description('Delete a requirement. Child requirements are detached from the deleted parent.')]
class DeleteRequirement extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'id' => 'required|string|owned_requirement',
        ]);

        $requirement = Requirement::findOrFail($data['id']);
        $children = $requirement->children()->count();
        $requirement->delete();

        return Response::structured([
            'id' => $data['id'],
            'deleted' => true,
            'children_detached' => $children,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->description('Requirement ULID to delete')->required(),
        ];
    }
}
