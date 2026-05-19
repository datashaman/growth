<?php

namespace App\Mcp\Tools\Plan;

use App\Growth\Transitions\ClosePlan as ClosePlanTransition;
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
#[Description('Close a project plan: move it from active to closed. Rejects a plan that is not active with a clear message. Records a status transition with the acting user and timestamp.')]
class ClosePlan extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'project_plan_id' => 'required|string|owned_project_plan',
            'reason' => 'nullable|string|max:1000',
        ]);

        $plan = ProjectPlan::findOrFail($data['project_plan_id']);

        try {
            $transition = (new ClosePlanTransition)->apply($plan, auth()->user(), $data['reason'] ?? null);
        } catch (IllegalTransitionException $e) {
            return new ResponseFactory(Response::error($e->getMessage()));
        }

        return Response::structured([
            'project_plan_id' => $plan->id,
            'from_status' => $transition->from_status,
            'to_status' => $transition->to_status,
            'transition_id' => $transition->id,
            'transitioned_at' => $transition->transitioned_at->toIso8601String(),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'project_plan_id' => $schema->string()->description('ProjectPlan ULID')->required(),
            'reason' => $schema->string()->description('Optional note recorded with the transition'),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'project_plan_id' => $schema->string()->required(),
            'from_status' => $schema->string()->required(),
            'to_status' => $schema->string()->required(),
            'transition_id' => $schema->string()->required(),
            'transitioned_at' => $schema->string()->required(),
        ];
    }
}
