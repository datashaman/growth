<?php

namespace App\Mcp\Tools\Plan;

use App\Models\ProjectPlanBaseline;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('List Project Management Plan baselines for a plan, newest first. Returns version metadata and the stored snapshot.')]
class ListPlanBaselines extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'project_plan_id' => 'required|string|owned_project_plan',
            'limit' => 'nullable|integer|min:1|max:100',
            'offset' => 'nullable|integer|min:0',
        ]);

        $limit = $data['limit'] ?? 50;
        $offset = $data['offset'] ?? 0;

        $query = ProjectPlanBaseline::query()
            ->where('project_plan_id', $data['project_plan_id']);

        $total = (clone $query)->count();

        $rows = $query
            ->orderByDesc('version')
            ->limit($limit)
            ->offset($offset)
            ->get();

        return Response::structured([
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset,
            'results' => $rows->map(fn ($baseline) => [
                'id' => $baseline->id,
                'project_plan_id' => $baseline->project_plan_id,
                'version' => $baseline->version,
                'baselined_at' => $baseline->baselined_at->toIso8601String(),
                'baselined_by_user_id' => $baseline->baselined_by_user_id,
                'baselined_by_agent_id' => $baseline->baselined_by_agent_id,
                'note' => $baseline->note,
                'snapshot' => $baseline->snapshot,
            ])->all(),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'project_plan_id' => $schema->string()
                ->description('ProjectPlan ULID')
                ->required(),
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
