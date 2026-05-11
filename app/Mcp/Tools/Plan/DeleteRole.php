<?php

namespace App\Mcp\Tools\Plan;

use App\Models\Role;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Delete a role. Work items previously owned by this role have their responsible_role_id cleared (nullOnDelete).')]
class DeleteRole extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'id' => 'required|string|owned_role',
        ]);

        $role = Role::findOrFail($data['id']);
        $orphaned = $role->workItems()->count();
        $role->delete();

        return Response::structured([
            'id' => $data['id'],
            'deleted' => true,
            'work_items_orphaned' => $orphaned,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()
                ->description('Role ULID')
                ->required(),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->required(),
            'deleted' => $schema->boolean()->required(),
            'work_items_orphaned' => $schema->integer()->required(),
        ];
    }
}
