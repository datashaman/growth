<?php

namespace App\Mcp\Tools\Plan;

use App\Models\Project;
use App\Models\WorkItem;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('List work items for a project. Filterable by kind, status, responsible_role_id, parent_id, and substring. "root_only=true" returns only top-level deliverables. For the requirements, milestones, and roles a work item is linked to, use `trace-query` with the work-item id. When architecture context is available, inspect `list-architecture-views` and `list-architecture-elements` before implementing work items.')]
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
        $architectureViews = Project::findOrFail($data['project_id'])
            ->designViews()
            ->withCount('elements')
            ->orderBy('viewpoint')
            ->orderBy('name')
            ->limit(5)
            ->get(['id', 'project_id', 'viewpoint', 'name']);

        $rows = $query
            ->orderBy('kind')
            ->orderBy('name')
            ->limit($limit)
            ->offset($offset)
            ->get([
                'id', 'number', 'kind', 'name', 'status', 'parent_id', 'responsible_role_id',
            ]);

        return Response::structured([
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset,
            'results' => $rows->map(fn ($w) => [
                'id' => $w->id,
                'number' => $w->number,
                'reference' => $w->reference(),
                'kind' => $w->kind,
                'name' => $w->name,
                'status' => $w->status,
                'parent_id' => $w->parent_id,
                'responsible_role_id' => $w->responsible_role_id,
            ])->all(),
            'architecture_context' => [
                'available' => $architectureViews->isNotEmpty(),
                'guidance' => $architectureViews->isNotEmpty()
                    ? 'Architecture content is agent-facing design context. Before implementing these work items, inspect the relevant views and elements with list-architecture-views, list-architecture-elements, and trace-query.'
                    : 'No architecture views are captured for this project yet.',
                'tools' => ['list-architecture-views', 'list-architecture-elements', 'trace-query'],
                'views' => $architectureViews->map(fn ($view): array => [
                    'id' => $view->id,
                    'viewpoint' => $view->viewpoint,
                    'name' => $view->name,
                    'elements_count' => $view->elements_count,
                ])->all(),
            ],
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
            'architecture_context' => $schema->object()->required(),
        ];
    }
}
