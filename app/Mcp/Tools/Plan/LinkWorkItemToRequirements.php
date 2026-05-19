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
#[Description('Link a work item to one or more requirements ("this work item covers these requirements"). Idempotent — pre-existing links are kept, new ones are added.')]
class LinkWorkItemToRequirements extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'work_item_id' => 'required|string|owned_work_item',
            'requirement_ids' => 'required|array|min:1',
            'requirement_ids.*' => 'required|string|owned_requirement',
        ]);

        $item = WorkItem::findOrFail($data['work_item_id']);
        $result = $item->requirements()->syncWithoutDetaching($data['requirement_ids']);

        return Response::structured([
            'work_item_id' => $item->id,
            'attached' => count($result['attached']),
            'updated' => count($result['updated']),
            'unchanged' => count($data['requirement_ids']) - count($result['attached']),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'work_item_id' => $schema->string()
                ->description('WorkItem ULID')
                ->required(),
            'requirement_ids' => $schema->array()
                ->description('Requirement ULIDs to link')
                ->items($schema->string())
                ->required(),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'work_item_id' => $schema->string()->required(),
            'attached' => $schema->integer()->required(),
            'updated' => $schema->integer()->required(),
            'unchanged' => $schema->integer()->required(),
        ];
    }
}
