<?php

namespace App\Mcp\Tools;

use App\Models\WorkItem;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Create or update a work item. Use parent_id to nest work under larger deliverables.')]
class UpsertWorkItem extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'id' => 'nullable|string|owned_work_item',
            'project_id' => 'required|string|owned_project',
            'parent_id' => 'nullable|string|owned_work_item',
            'responsible_role_id' => 'nullable|string|owned_role',
            'kind' => 'required|in:'.implode(',', WorkItem::KINDS),
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'nullable|in:'.implode(',', WorkItem::STATUSES),
            'planned_start_date' => 'nullable|date_format:Y-m-d',
            'due_date' => 'nullable|date_format:Y-m-d',
            'effort_estimate' => 'nullable|string|max:60',
            'effort_estimate_hours' => 'nullable|numeric|min:0|max:999999',
            'effort_actual' => 'nullable|string|max:60',
            'effort_actual_hours' => 'nullable|numeric|min:0|max:999999',
            'cost_estimate' => 'nullable|string|max:60',
            'cost_estimate_amount' => 'nullable|numeric|min:0|max:9999999999',
            'cost_actual' => 'nullable|string|max:60',
            'cost_actual_amount' => 'nullable|numeric|min:0|max:9999999999',
            'cost_currency' => 'nullable|string|size:3',
        ]);

        $id = $data['id'] ?? null;
        unset($data['id']);

        $item = $id ? tap(WorkItem::findOrFail($id))->update($data) : WorkItem::create($data);

        return Response::structured([
            'id' => $item->id,
            'kind' => $item->kind,
            'name' => $item->name,
            'parent_id' => $item->parent_id,
            'status' => $item->status,
            'planned_start_date' => $item->planned_start_date?->toDateString(),
            'due_date' => $item->due_date?->toDateString(),
            'effort_estimate_hours' => $item->effort_estimate_hours,
            'effort_actual_hours' => $item->effort_actual_hours,
            'cost_estimate_amount' => $item->cost_estimate_amount,
            'cost_actual_amount' => $item->cost_actual_amount,
            'cost_currency' => $item->cost_currency,
            'created' => $item->wasRecentlyCreated,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->description('Existing work item ULID. Omit to create.'),
            'project_id' => $schema->string()->description('Project ULID')->required(),
            'parent_id' => $schema->string()->description('Parent work item ULID'),
            'responsible_role_id' => $schema->string()->description('Responsible role ULID'),
            'kind' => $schema->string()->description('Work item kind')->enum(WorkItem::KINDS)->required(),
            'name' => $schema->string()->description('Short label')->required(),
            'description' => $schema->string()->description('Optional details or acceptance notes'),
            'status' => $schema->string()->description('Tracking status')->enum(WorkItem::STATUSES),
            'planned_start_date' => $schema->string()->description('Planned start date in YYYY-MM-DD format'),
            'due_date' => $schema->string()->description('Due date in YYYY-MM-DD format'),
            'effort_estimate' => $schema->string()->description('Free-form effort estimate'),
            'effort_estimate_hours' => $schema->number()->description('Estimated effort in hours'),
            'effort_actual' => $schema->string()->description('Free-form actual effort'),
            'effort_actual_hours' => $schema->number()->description('Actual effort in hours'),
            'cost_estimate' => $schema->string()->description('Free-form cost estimate'),
            'cost_estimate_amount' => $schema->number()->description('Estimated cost amount'),
            'cost_actual' => $schema->string()->description('Free-form actual cost'),
            'cost_actual_amount' => $schema->number()->description('Actual cost amount'),
            'cost_currency' => $schema->string()->description('Three-letter currency code for numeric cost amounts, such as USD'),
        ];
    }
}
