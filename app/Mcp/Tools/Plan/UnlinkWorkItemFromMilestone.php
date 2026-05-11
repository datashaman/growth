<?php

namespace App\Mcp\Tools\Plan;

use App\Models\WorkItem;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Drop the link between a work item and a milestone.')]
class UnlinkWorkItemFromMilestone extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'work_item_id' => 'required|string|owned_work_item',
            'milestone_id' => 'required|string|owned_milestone',
        ]);

        $detached = WorkItem::findOrFail($data['work_item_id'])
            ->milestones()
            ->detach($data['milestone_id']);

        return Response::structured([
            'work_item_id' => $data['work_item_id'],
            'milestone_id' => $data['milestone_id'],
            'detached' => $detached > 0,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'work_item_id' => $schema->string()
                ->description('WorkItem ULID')
                ->required(),
            'milestone_id' => $schema->string()
                ->description('Milestone ULID')
                ->required(),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'work_item_id' => $schema->string()->required(),
            'milestone_id' => $schema->string()->required(),
            'detached' => $schema->boolean()->required(),
        ];
    }
}
