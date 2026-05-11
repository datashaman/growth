<?php

namespace App\Mcp\Tools\Plan;

use App\Models\WorkItem;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Declare that work_item depends on depends_on. Default kind is finish_to_start (predecessor must finish before this one starts). Self-dependencies are rejected. Idempotent on (work_item_id, depends_on_id).')]
class LinkWorkItemDependency extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'work_item_id' => 'required|string|owned_work_item|different:depends_on_id',
            'depends_on_id' => 'required|string|owned_work_item',
            'kind' => 'nullable|in:'.implode(',', WorkItem::DEPENDENCY_KINDS),
        ]);

        $kind = $data['kind'] ?? 'finish_to_start';
        $item = WorkItem::findOrFail($data['work_item_id']);
        $result = $item->dependencies()->syncWithoutDetaching([
            $data['depends_on_id'] => ['kind' => $kind],
        ]);

        return Response::structured([
            'work_item_id' => $item->id,
            'depends_on_id' => $data['depends_on_id'],
            'kind' => $kind,
            'attached' => $result['attached'] !== [],
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'work_item_id' => $schema->string()->description('Dependent WorkItem ULID')->required(),
            'depends_on_id' => $schema->string()->description('Predecessor WorkItem ULID')->required(),
            'kind' => $schema->string()
                ->description('Dependency type (default finish_to_start)')
                ->enum(WorkItem::DEPENDENCY_KINDS),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'work_item_id' => $schema->string()->required(),
            'depends_on_id' => $schema->string()->required(),
            'kind' => $schema->string()->required(),
            'attached' => $schema->boolean()->required(),
        ];
    }
}
