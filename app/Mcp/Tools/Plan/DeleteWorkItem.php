<?php

namespace App\Mcp\Tools\Plan;

use App\Models\WorkItem;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[IsDestructive]
#[Description('Delete a work item. Children promote to root (parent_id nulled). Requirement and milestone links cascade-drop. Citations cascade-drop.')]
class DeleteWorkItem extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'id' => 'required|string|owned_work_item',
        ]);

        $item = WorkItem::findOrFail($data['id']);
        $children = $item->children()->count();
        $reqs = $item->requirements()->count();
        $mss = $item->milestones()->count();
        $item->delete();

        return Response::structured([
            'id' => $data['id'],
            'deleted' => true,
            'children_orphaned' => $children,
            'requirement_links_dropped' => $reqs,
            'milestone_links_dropped' => $mss,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()
                ->description('WorkItem ULID')
                ->required(),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->required(),
            'deleted' => $schema->boolean()->required(),
            'children_orphaned' => $schema->integer()->required(),
            'requirement_links_dropped' => $schema->integer()->required(),
            'milestone_links_dropped' => $schema->integer()->required(),
        ];
    }
}
