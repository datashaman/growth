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
#[Description('Drop a work-item dependency edge.')]
class UnlinkWorkItemDependency extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'work_item_id' => 'required|string|owned_work_item',
            'depends_on_id' => 'required|string|owned_work_item',
        ]);

        $detached = WorkItem::findOrFail($data['work_item_id'])
            ->dependencies()
            ->detach($data['depends_on_id']);

        return Response::structured([
            'work_item_id' => $data['work_item_id'],
            'depends_on_id' => $data['depends_on_id'],
            'detached' => $detached > 0,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'work_item_id' => $schema->string()->description('Dependent WorkItem ULID')->required(),
            'depends_on_id' => $schema->string()->description('Predecessor WorkItem ULID')->required(),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'work_item_id' => $schema->string()->required(),
            'depends_on_id' => $schema->string()->required(),
            'detached' => $schema->boolean()->required(),
        ];
    }
}
