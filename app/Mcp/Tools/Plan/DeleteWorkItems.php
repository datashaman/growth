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
#[Description('Delete work items by filter. Currently supports id=[...] for up to 100 work item ULIDs. Children promote to root (parent_id nulled). Requirement and milestone links cascade-drop. Citations cascade-drop.')]
class DeleteWorkItems extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'id' => 'required|array|min:1|max:100',
            'id.*' => 'required|string|distinct|owned_work_item',
        ], [
            'id.max' => 'Batches are capped at 100 ids per call. Split into smaller batches.',
        ]);

        $items = WorkItem::whereIn('id', $data['id'])->get()->keyBy('id');

        $deleted = [];
        foreach ($data['id'] as $id) {
            /** @var WorkItem $item */
            $item = $items->get($id);
            $children = $item->children()->count();
            $requirements = $item->requirements()->count();
            $milestones = $item->milestones()->count();
            $item->delete();

            $deleted[] = [
                'id' => $id,
                'deleted' => true,
                'children_orphaned' => $children,
                'requirement_links_dropped' => $requirements,
                'milestone_links_dropped' => $milestones,
            ];
        }

        return Response::structured([
            'filters' => ['id' => $data['id']],
            'deleted_count' => count($deleted),
            'deleted' => $deleted,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->array()
                ->items($schema->string())
                ->min(1)
                ->max(100)
                ->description('Work item ULIDs to delete. This is the first supported delete filter: id=[...].')
                ->required(),
        ];
    }
}
