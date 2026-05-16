<?php

namespace App\Mcp\Tools\Plan;

use App\Growth\Transitions\DeferMilestone as DeferMilestoneTransition;
use App\Growth\Transitions\IllegalTransitionException;
use App\Models\Milestone;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Defer a milestone: move it from pending to deferred and set a new target date. Rejects a milestone that is not pending with a clear message. Records a status transition with the acting user and timestamp.')]
class DeferMilestone extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'milestone_id' => 'required|string|owned_milestone',
            'new_target_date' => 'required|date_format:Y-m-d',
            'reason' => 'nullable|string|max:1000',
        ]);

        $milestone = Milestone::findOrFail($data['milestone_id']);

        try {
            $transition = (new DeferMilestoneTransition($data['new_target_date']))
                ->apply($milestone, auth()->user(), $data['reason'] ?? null);
        } catch (IllegalTransitionException $e) {
            return new ResponseFactory(Response::error($e->getMessage()));
        }

        return Response::structured([
            'milestone_id' => $milestone->id,
            'from_status' => $transition->from_status,
            'to_status' => $transition->to_status,
            'new_target_date' => $milestone->fresh()->target_date?->toDateString(),
            'transition_id' => $transition->id,
            'transitioned_at' => $transition->transitioned_at->toIso8601String(),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'milestone_id' => $schema->string()->description('Milestone ULID')->required(),
            'new_target_date' => $schema->string()->description('New target date in YYYY-MM-DD format')->required(),
            'reason' => $schema->string()->description('Optional note recorded with the transition'),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'milestone_id' => $schema->string()->required(),
            'from_status' => $schema->string()->required(),
            'to_status' => $schema->string()->required(),
            'new_target_date' => $schema->string()->required(),
            'transition_id' => $schema->string()->required(),
            'transitioned_at' => $schema->string()->required(),
        ];
    }
}
