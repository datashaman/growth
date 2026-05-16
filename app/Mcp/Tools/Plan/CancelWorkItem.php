<?php

namespace App\Mcp\Tools\Plan;

use App\Growth\Transitions\CancelWorkItem as CancelWorkItemTransition;
use App\Growth\Transitions\IllegalTransitionException;
use App\Models\WorkItem;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Cancel a work item: move it from todo, in_progress, or blocked to cancelled. Rejects an already done or cancelled work item with a clear message. Records a status transition with the acting user and timestamp.')]
class CancelWorkItem extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'work_item_id' => 'required|string|owned_work_item',
            'reason' => 'nullable|string|max:1000',
        ]);

        $workItem = WorkItem::findOrFail($data['work_item_id']);

        try {
            $transition = (new CancelWorkItemTransition)->apply($workItem, auth()->user(), $data['reason'] ?? null);
        } catch (IllegalTransitionException $e) {
            return new ResponseFactory(Response::error($e->getMessage()));
        }

        return Response::structured([
            'work_item_id' => $workItem->id,
            'from_status' => $transition->from_status,
            'to_status' => $transition->to_status,
            'transition_id' => $transition->id,
            'transitioned_at' => $transition->transitioned_at->toIso8601String(),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'work_item_id' => $schema->string()->description('WorkItem ULID')->required(),
            'reason' => $schema->string()->description('Optional note recorded with the transition'),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'work_item_id' => $schema->string()->required(),
            'from_status' => $schema->string()->required(),
            'to_status' => $schema->string()->required(),
            'transition_id' => $schema->string()->required(),
            'transitioned_at' => $schema->string()->required(),
        ];
    }
}
