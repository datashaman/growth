<?php

namespace App\Mcp\Tools\Plan;

use App\Growth\Plan\PlanBaseliner;
use App\Growth\Transitions\IllegalTransitionException;
use App\Models\ProjectPlan;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[IsDestructive(false)]
#[Description('Create an immutable baseline snapshot of the current Project Management Plan and its WBS state. Auto-increments version and moves the plan from draft to baselined, recording a status transition. Rejects a plan that is not in draft.')]
class BaselinePlan extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'project_plan_id' => 'required|string|owned_project_plan',
            'note' => 'nullable|string',
        ]);

        $plan = ProjectPlan::findOrFail($data['project_plan_id']);

        try {
            $baseline = app(PlanBaseliner::class)->baseline($plan, auth()->user(), $data['note'] ?? null);
        } catch (IllegalTransitionException $e) {
            return new ResponseFactory(Response::error($e->getMessage()));
        }

        return Response::structured([
            'id' => $baseline->id,
            'project_plan_id' => $baseline->project_plan_id,
            'version' => $baseline->version,
            'baselined_at' => $baseline->baselined_at->toIso8601String(),
            'note' => $baseline->note,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'project_plan_id' => $schema->string()
                ->description('ProjectPlan ULID to baseline')
                ->required(),
            'note' => $schema->string()
                ->description('Optional baseline note / decision record'),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->required(),
            'project_plan_id' => $schema->string()->required(),
            'version' => $schema->integer()->required(),
            'baselined_at' => $schema->string()->required(),
            'note' => $schema->string(),
        ];
    }
}
