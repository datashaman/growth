<?php

namespace App\Mcp\Tools\Plan;

use App\Models\Milestone;
use App\Models\WorkItem;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[IsDestructive(false)]
#[Description('Link a work item to a milestone ("this item must be done to hit this milestone"). Idempotent.')]
class LinkWorkItemToMilestone extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'work_item_id' => 'required|string|owned_work_item',
            'milestone_id' => 'required|string|owned_milestone',
        ]);

        $workItem = WorkItem::findOrFail($data['work_item_id']);
        $milestone = Milestone::findOrFail($data['milestone_id']);

        // A milestone is a scope bundle within one project; linking a work
        // item from another project would let one project's milestone depend
        // on unrelated work. Both ids are workspace-owned, not same-project.
        if ($milestone->project_id !== $workItem->project_id) {
            throw ValidationException::withMessages([
                'milestone_id' => 'A work item can only be linked to a milestone in the same project.',
            ]);
        }

        $result = $workItem->milestones()->syncWithoutDetaching([$milestone->id]);

        return Response::structured([
            'work_item_id' => $data['work_item_id'],
            'milestone_id' => $data['milestone_id'],
            'attached' => $result['attached'] !== [],
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'work_item_id' => $schema->string()
                ->description('WorkItem ULID')
                ->required(),
            'milestone_id' => $schema->string()
                ->description('Milestone ULID')
                ->required(),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'work_item_id' => $schema->string()->required(),
            'milestone_id' => $schema->string()->required(),
            'attached' => $schema->boolean()->required(),
        ];
    }
}
