<?php

namespace App\Mcp\Tools\Plan;

use App\Models\WorkItem;
use App\Models\WorkItemDeliveryLink;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('List implementation evidence links for one work item or all work items in a project.')]
class ListWorkItemDeliveryLinks extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'project_id' => 'required_without:work_item_id|nullable|string|owned_project',
            'work_item_id' => 'nullable|string|owned_work_item',
            'type' => 'nullable|in:'.implode(',', WorkItemDeliveryLink::TYPES),
            'limit' => 'nullable|integer|min:1|max:200',
            'offset' => 'nullable|integer|min:0',
        ]);

        $limit = $data['limit'] ?? 50;
        $offset = $data['offset'] ?? 0;

        $query = WorkItemDeliveryLink::query()->with('workItem:id,project_id,name');
        if (isset($data['work_item_id'])) {
            $query->where('work_item_id', $data['work_item_id']);
        } else {
            $query->whereIn('work_item_id', WorkItem::where('project_id', $data['project_id'])->pluck('id'));
        }
        if (isset($data['type'])) {
            $query->where('type', $data['type']);
        }

        $total = (clone $query)->count();

        $rows = $query->orderBy('type')->orderBy('ref')
            ->limit($limit)
            ->offset($offset)
            ->get();

        return Response::structured([
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset,
            'results' => $rows->map(fn (WorkItemDeliveryLink $link): array => [
                'id' => $link->id,
                'work_item_id' => $link->work_item_id,
                'work_item' => $link->workItem?->name,
                'type' => $link->type,
                'ref' => $link->ref,
                'url' => $link->url,
                'description' => $link->description,
            ])->all(),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'project_id' => $schema->string()->description('Project ULID. Required unless work_item_id is provided.'),
            'work_item_id' => $schema->string()->description('Work item ULID'),
            'type' => $schema->string()->description('Filter by delivery link type')->enum(WorkItemDeliveryLink::TYPES),
            'limit' => $schema->integer()->description('Page size (1-200, default 50)'),
            'offset' => $schema->integer()->description('Offset for pagination (default 0)'),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'total' => $schema->integer()->required(),
            'limit' => $schema->integer()->required(),
            'offset' => $schema->integer()->required(),
            'results' => $schema->array()->required(),
        ];
    }
}
