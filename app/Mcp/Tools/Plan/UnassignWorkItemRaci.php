<?php

namespace App\Mcp\Tools\Plan;

use App\Models\WorkItem;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Remove a single RACI assignment row by (work_item, role, raci letter).')]
class UnassignWorkItemRaci extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'work_item_id' => 'required|string|owned_work_item',
            'role_id' => 'required|string|owned_role',
            'raci' => 'required|in:'.implode(',', WorkItem::RACI),
        ]);

        $detached = WorkItem::findOrFail($data['work_item_id'])
            ->raciRoles()
            ->wherePivot('raci', $data['raci'])
            ->detach($data['role_id']);

        return Response::structured([
            'work_item_id' => $data['work_item_id'],
            'role_id' => $data['role_id'],
            'raci' => $data['raci'],
            'detached' => $detached > 0,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'work_item_id' => $schema->string()->description('WorkItem ULID')->required(),
            'role_id' => $schema->string()->description('Role ULID')->required(),
            'raci' => $schema->string()->description('Which RACI row to drop')->enum(WorkItem::RACI)->required(),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'work_item_id' => $schema->string()->required(),
            'role_id' => $schema->string()->required(),
            'raci' => $schema->string()->required(),
            'detached' => $schema->boolean()->required(),
        ];
    }
}
