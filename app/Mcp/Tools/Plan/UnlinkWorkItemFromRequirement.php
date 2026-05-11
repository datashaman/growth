<?php

namespace App\Mcp\Tools\Plan;

use App\Models\WorkItem;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Drop the link between a work item and a single requirement. Neither artifact is deleted.')]
class UnlinkWorkItemFromRequirement extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'work_item_id' => 'required|string|owned_work_item',
            'requirement_id' => 'required|string|owned_requirement',
        ]);

        $detached = WorkItem::findOrFail($data['work_item_id'])
            ->requirements()
            ->detach($data['requirement_id']);

        return Response::structured([
            'work_item_id' => $data['work_item_id'],
            'requirement_id' => $data['requirement_id'],
            'detached' => $detached > 0,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'work_item_id' => $schema->string()
                ->description('WorkItem ULID')
                ->required(),
            'requirement_id' => $schema->string()
                ->description('Requirement ULID to unlink')
                ->required(),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'work_item_id' => $schema->string()->required(),
            'requirement_id' => $schema->string()->required(),
            'detached' => $schema->boolean()->required(),
        ];
    }
}
