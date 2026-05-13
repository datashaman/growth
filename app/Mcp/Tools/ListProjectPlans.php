<?php

namespace App\Mcp\Tools;

use App\Models\ProjectPlan;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('List Project Management Plans for a project, newest first. Use this to discover the project_plan_id values that baseline-plan and list-plan-baselines require.')]
class ListProjectPlans extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'project_id' => 'required|string|owned_project',
            'status' => 'nullable|in:'.implode(',', ProjectPlan::STATUSES),
            'limit' => 'nullable|integer|min:1|max:100',
            'offset' => 'nullable|integer|min:0',
        ]);

        $limit = $data['limit'] ?? 50;
        $offset = $data['offset'] ?? 0;

        $query = ProjectPlan::query()->where('project_id', $data['project_id']);

        if (isset($data['status'])) {
            $query->where('status', $data['status']);
        }

        $total = (clone $query)->count();

        $rows = $query
            ->latest('updated_at')
            ->limit($limit)
            ->offset($offset)
            ->get();

        return Response::structured([
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset,
            'results' => $rows->map(fn (ProjectPlan $plan) => [
                'id' => $plan->id,
                'project_id' => $plan->project_id,
                'status' => $plan->status,
                'scope_summary' => $plan->scope_summary,
                'updated_at' => $plan->updated_at?->toIso8601String(),
            ])->all(),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'project_id' => $schema->string()
                ->description('Project ULID')
                ->required(),
            'status' => $schema->string()
                ->description('Filter by status')
                ->enum(ProjectPlan::STATUSES),
            'limit' => $schema->integer()->description('Page size (1-100, default 50)'),
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
