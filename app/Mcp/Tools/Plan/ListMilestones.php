<?php

namespace App\Mcp\Tools\Plan;

use App\Growth\Assurance\MilestoneGateEvaluator;
use App\Models\Milestone;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('List milestones for a project. Returns narrow columns plus each milestone\'s readiness gate (pass/warn/fail with error and warning counts), sorted by name.')]
class ListMilestones extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'project_id' => 'required|string|owned_project',
            'status' => 'nullable|in:'.implode(',', Milestone::STATUSES),
            'q' => 'nullable|string|max:255',
            'limit' => 'nullable|integer|min:1|max:200',
            'offset' => 'nullable|integer|min:0',
        ]);

        $limit = $data['limit'] ?? 50;
        $offset = $data['offset'] ?? 0;

        $query = Milestone::query()->where('project_id', $data['project_id']);
        if (isset($data['status'])) {
            $query->where('status', $data['status']);
        }
        if (isset($data['q'])) {
            $query->where('name', 'like', '%'.$data['q'].'%');
        }

        $total = (clone $query)->count();

        $rows = $query
            ->orderBy('name')
            ->withCount('workItems')
            ->with([
                'project',
                'workItems.deliveryLinks.checkRuns',
                'workItems.deliveryLinks.deployments',
                // The milestone gate's adoption check only reads each work
                // item's `done` transitions — constrain the load to those.
                'workItems.statusTransitions' => fn ($query) => $query
                    ->where('to_status', 'done')
                    ->select('transitionable_type', 'transitionable_id', 'to_status', 'transitioned_at'),
            ])
            ->limit($limit)
            ->offset($offset)
            ->get(['id', 'name', 'status', 'exit_criteria']);

        $evaluator = app(MilestoneGateEvaluator::class);

        return Response::structured([
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset,
            'results' => $rows->map(function ($m) use ($evaluator) {
                $gate = $evaluator->evaluate($m);

                return [
                    'id' => $m->id,
                    'name' => $m->name,
                    'status' => $m->status,
                    'exit_criteria' => $m->exit_criteria,
                    'work_items_count' => $m->work_items_count,
                    'gate' => [
                        'status' => $gate['status'],
                        'errors' => $gate['errors'],
                        'warnings' => $gate['warnings'],
                    ],
                ];
            })->all(),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'project_id' => $schema->string()->description('Project ULID')->required(),
            'status' => $schema->string()->description('Filter by milestone status')->enum(Milestone::STATUSES),
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
