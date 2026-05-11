<?php

namespace App\Mcp\Tools\Plan;

use App\Models\WorkItem;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Link a work item to a milestone ("this item must be done to hit this milestone"). Idempotent.')]
class LinkWorkItemToMilestone extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'work_item_id' => 'required|string|owned_work_item',
            'milestone_id' => 'required|string|owned_milestone',
        ]);

        $result = WorkItem::findOrFail($data['work_item_id'])
            ->milestones()
            ->syncWithoutDetaching([$data['milestone_id']]);

        return Response::structured([
            'work_item_id' => $data['work_item_id'],
            'milestone_id' => $data['milestone_id'],
            'attached' => $result['attached'] !== [],
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
            'attached' => $schema->boolean()->required(),
        ];
    }
}
