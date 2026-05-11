<?php

namespace App\Mcp\Tools\Plan;

use App\Models\WorkItem;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('List work items for a project. Filterable by kind, status, responsible_role_id, parent_id, and substring. "root_only=true" returns only top-level deliverables.')]
class ListWorkItems extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'project_id' => 'required|string|owned_project',
            'kind' => 'nullable|in:'.implode(',', WorkItem::KINDS),
            'status' => 'nullable|in:'.implode(',', WorkItem::STATUSES),
            'responsible_role_id' => 'nullable|string|owned_role',
            'parent_id' => 'nullable|string|owned_work_item',
            'root_only' => 'nullable|boolean',
            'q' => 'nullable|string|max:255',
            'limit' => 'nullable|integer|min:1|max:200',
            'offset' => 'nullable|integer|min:0',
        ]);

        $limit = $data['limit'] ?? 50;
        $offset = $data['offset'] ?? 0;

        $query = WorkItem::query()->where('project_id', $data['project_id']);

        foreach (['kind', 'status', 'responsible_role_id', 'parent_id'] as $field) {
            if (isset($data[$field])) {
                $query->where($field, $data[$field]);
            }
        }
        if (! empty($data['root_only'])) {
            $query->whereNull('parent_id');
        }
        if (isset($data['q'])) {
            $query->where('name', 'like', '%'.$data['q'].'%');
        }

        $total = (clone $query)->count();

        $rows = $query
            ->orderBy('kind')
            ->orderBy('name')
            ->limit($limit)
            ->offset($offset)
            ->get([
                'id', 'kind', 'name', 'status', 'parent_id', 'responsible_role_id',
                'planned_start_date', 'due_date', 'effort_estimate', 'effort_estimate_hours', 'effort_actual',
                'effort_actual_hours', 'cost_estimate', 'cost_estimate_amount',
                'cost_actual', 'cost_actual_amount', 'cost_currency',
            ]);

        return Response::structured([
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset,
            'results' => $rows->map(fn ($w) => [
                'id' => $w->id,
                'kind' => $w->kind,
                'name' => $w->name,
                'status' => $w->status,
                'parent_id' => $w->parent_id,
                'responsible_role_id' => $w->responsible_role_id,
                'planned_start_date' => $w->planned_start_date?->toDateString(),
                'due_date' => $w->due_date?->toDateString(),
                'effort_estimate' => $w->effort_estimate,
                'effort_estimate_hours' => $w->effort_estimate_hours,
                'effort_actual' => $w->effort_actual,
                'effort_actual_hours' => $w->effort_actual_hours,
                'cost_estimate' => $w->cost_estimate,
                'cost_estimate_amount' => $w->cost_estimate_amount,
                'cost_actual' => $w->cost_actual,
                'cost_actual_amount' => $w->cost_actual_amount,
                'cost_currency' => $w->cost_currency,
            ])->all(),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'project_id' => $schema->string()->description('Project ULID')->required(),
            'kind' => $schema->string()->description('Filter by WBS level')->enum(WorkItem::KINDS),
            'status' => $schema->string()->description('Filter by tracking status')->enum(WorkItem::STATUSES),
            'responsible_role_id' => $schema->string()->description('Filter by responsible role'),
            'parent_id' => $schema->string()->description('Filter to direct children of this parent'),
            'root_only' => $schema->boolean()->description('If true, only top-level work items (no parent)'),
            'q' => $schema->string()->description('Substring match on name'),
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
