<?php

namespace App\Mcp\Tools\Requirements;

use App\Models\Requirement;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Delete a requirement. Children are orphaned (parent_id set to null); test-case traces and anomaly affects are removed via the pivots. No soft delete — use with care.')]
class DeleteRequirement extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'id' => 'required|string|owned_requirement',
        ]);

        $req = Requirement::findOrFail($data['id']);
        $orphanedCount = $req->children()->count();
        $req->delete();

        return Response::structured([
            'id' => $data['id'],
            'deleted' => true,
            'children_orphaned' => $orphanedCount,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()
                ->description('Requirement ULID to delete')
                ->required(),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->required(),
            'deleted' => $schema->boolean()->required(),
            'children_orphaned' => $schema->integer()->required(),
        ];
    }
}
