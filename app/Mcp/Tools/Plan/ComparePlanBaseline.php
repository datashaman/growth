<?php

namespace App\Mcp\Tools\Plan;

use App\Growth\Baselines\PlanBaselineDiffer;
use App\Models\ProjectPlanBaseline;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Compare a Project Management Plan baseline snapshot to the current plan/WBS state and report changed artifacts, per-field before/after values, and approved-change coverage.')]
class ComparePlanBaseline extends Tool
{
    public function __construct(private readonly PlanBaselineDiffer $differ) {}

    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'baseline_id' => 'required|string|owned_project_plan_baseline',
        ]);

        $baseline = ProjectPlanBaseline::findOrFail($data['baseline_id']);

        return Response::structured($this->differ->diff($baseline));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'baseline_id' => $schema->string()
                ->description('ProjectPlanBaseline ULID to compare against current plan/WBS state')
                ->required(),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'baseline_id' => $schema->string()->required(),
            'project_plan_id' => $schema->string()->required(),
            'version' => $schema->integer()->required(),
            'summary' => $schema->object()->required(),
            'project_plan' => $schema->array()->required(),
            'work_items' => $schema->array()->required(),
        ];
    }
}
