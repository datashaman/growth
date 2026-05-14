<?php

namespace App\Mcp\Tools\Plan;

use App\Models\CheckRunEvidence;
use App\Models\WorkItem;
use App\Models\WorkItemDeliveryLink;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('List CI/check-run evidence for a project, delivery link, or work item. Filterable by status and conclusion.')]
class ListCheckRuns extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'project_id' => 'required_without_all:work_item_delivery_link_id,work_item_id|nullable|string|owned_project',
            'work_item_id' => 'nullable|string|owned_work_item',
            'work_item_delivery_link_id' => 'nullable|string|owned_work_item_delivery_link',
            'status' => 'nullable|in:'.implode(',', CheckRunEvidence::STATUSES),
            'conclusion' => 'nullable|in:'.implode(',', CheckRunEvidence::CONCLUSIONS),
            'limit' => 'nullable|integer|min:1|max:200',
            'offset' => 'nullable|integer|min:0',
        ]);

        $limit = $data['limit'] ?? 50;
        $offset = $data['offset'] ?? 0;

        $query = CheckRunEvidence::query()
            ->with('deliveryLink.workItem:id,project_id,name');

        if (isset($data['work_item_delivery_link_id'])) {
            $query->where('work_item_delivery_link_id', $data['work_item_delivery_link_id']);
        } elseif (isset($data['work_item_id'])) {
            $query->whereIn('work_item_delivery_link_id', WorkItemDeliveryLink::where('work_item_id', $data['work_item_id'])->pluck('id'));
        } else {
            $workItemIds = WorkItem::where('project_id', $data['project_id'])->pluck('id');
            $query->whereIn('work_item_delivery_link_id', WorkItemDeliveryLink::whereIn('work_item_id', $workItemIds)->pluck('id'));
        }

        foreach (['status', 'conclusion'] as $field) {
            if (isset($data[$field])) {
                $query->where($field, $data[$field]);
            }
        }

        $total = (clone $query)->count();

        $rows = $query->orderBy('status')->orderBy('name')
            ->limit($limit)
            ->offset($offset)
            ->get();

        return Response::structured([
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset,
            'results' => $rows->map(fn (CheckRunEvidence $check): array => [
                'id' => $check->id,
                'work_item_delivery_link_id' => $check->work_item_delivery_link_id,
                'work_item_id' => $check->deliveryLink?->work_item_id,
                'work_item' => $check->deliveryLink?->workItem?->name,
                'delivery_type' => $check->deliveryLink?->type,
                'delivery_ref' => $check->deliveryLink?->ref,
                'provider' => $check->provider,
                'name' => $check->name,
                'run_ref' => $check->run_ref,
                'status' => $check->status,
                'conclusion' => $check->conclusion,
                'url' => $check->url,
                'started_at' => $check->started_at?->toIso8601String(),
                'completed_at' => $check->completed_at?->toIso8601String(),
            ])->all(),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'project_id' => $schema->string()->description('Project ULID. Required unless work_item_id or delivery link id is provided.'),
            'work_item_id' => $schema->string()->description('Work item ULID'),
            'work_item_delivery_link_id' => $schema->string()->description('Delivery link ULID'),
            'status' => $schema->string()->description('Filter by check status')->enum(CheckRunEvidence::STATUSES),
            'conclusion' => $schema->string()->description('Filter by check conclusion')->enum(CheckRunEvidence::CONCLUSIONS),
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
