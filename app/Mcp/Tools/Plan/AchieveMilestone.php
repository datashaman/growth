<?php

namespace App\Mcp\Tools\Plan;

use App\Growth\Transitions\AchieveMilestone as AchieveMilestoneTransition;
use App\Growth\Transitions\IllegalTransitionException;
use App\Models\Milestone;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[IsDestructive(false)]
#[Description('Mark a milestone as achieved: move it from pending to achieved. Rejects a milestone that is not pending, or whose readiness gate is failing — the milestone must bundle at least one work item, every member work item must be done, and no done member may have failed checks. A gate that only warns (e.g. a done item with no delivery evidence) does not block. Failures come back with a clear message. Records a status transition with the acting user and timestamp.')]
class AchieveMilestone extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'milestone_id' => 'required|string|owned_milestone',
            'reason' => 'nullable|string|max:1000',
        ]);

        $milestone = Milestone::findOrFail($data['milestone_id']);

        try {
            $transition = (new AchieveMilestoneTransition)->apply($milestone, auth()->user(), $data['reason'] ?? null);
        } catch (IllegalTransitionException $e) {
            return new ResponseFactory(Response::error($e->getMessage()));
        }

        return Response::structured([
            'milestone_id' => $milestone->id,
            'from_status' => $transition->from_status,
            'to_status' => $transition->to_status,
            'transition_id' => $transition->id,
            'transitioned_at' => $transition->transitioned_at->toIso8601String(),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'milestone_id' => $schema->string()->description('Milestone ULID')->required(),
            'reason' => $schema->string()->description('Optional note recorded with the transition'),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'milestone_id' => $schema->string()->required(),
            'from_status' => $schema->string()->required(),
            'to_status' => $schema->string()->required(),
            'transition_id' => $schema->string()->required(),
            'transitioned_at' => $schema->string()->required(),
        ];
    }
}
