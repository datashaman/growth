<?php

namespace App\Mcp\Tools\Plan;

use App\Models\WorkItem;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[IsDestructive(false)]
#[Description('Attach a role to a work item under a RACI label (r/a/c/i). A single role can carry multiple labels on the same work item. Idempotent.')]
class AssignWorkItemRaci extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'work_item_id' => 'required|string|owned_work_item',
            'role_id' => 'required|string|owned_role',
            'raci' => 'required|in:'.implode(',', WorkItem::RACI),
        ]);

        $item = WorkItem::findOrFail($data['work_item_id']);

        $already = $item->raciRoles()
            ->wherePivot('role_id', $data['role_id'])
            ->wherePivot('raci', $data['raci'])
            ->exists();

        if (! $already) {
            $item->raciRoles()->attach($data['role_id'], ['raci' => $data['raci']]);
        }

        return Response::structured([
            'work_item_id' => $item->id,
            'role_id' => $data['role_id'],
            'raci' => $data['raci'],
            'attached' => ! $already,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'work_item_id' => $schema->string()->description('WorkItem ULID')->required(),
            'role_id' => $schema->string()->description('Role ULID')->required(),
            'raci' => $schema->string()
                ->description('r = Responsible, a = Accountable, c = Consulted, i = Informed')
                ->enum(WorkItem::RACI)
                ->required(),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'work_item_id' => $schema->string()->required(),
            'role_id' => $schema->string()->required(),
            'raci' => $schema->string()->required(),
            'attached' => $schema->boolean()->required(),
        ];
    }
}
