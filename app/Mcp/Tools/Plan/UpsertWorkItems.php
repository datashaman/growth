<?php

namespace App\Mcp\Tools\Plan;

use App\Models\WorkItem;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Throwable;

#[IsDestructive(false)]
#[Description('Create or update up to 100 work items in one call. Each item is committed independently — per-item validation or runtime failures are reported alongside successes without aborting the batch and without rolling back already-applied items. Status is not set here: new items start as todo and move only through the work item transition tools (start, complete, block, unblock, cancel, reopen).')]
class UpsertWorkItems extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $payload = $request->validate([
            'items' => 'required|array|min:1|max:100',
        ], [
            'items.max' => 'Batches are capped at 100 items per call. Split into smaller batches.',
        ]);

        $results = [];
        foreach ($payload['items'] as $index => $item) {
            $results[] = $this->upsertItem((int) $index, is_array($item) ? $item : []);
        }

        return Response::structured(['items' => $results]);
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function upsertItem(int $index, array $item): array
    {
        try {
            $data = Validator::make($item, $this->itemRules(), $this->itemMessages())->validate();
        } catch (ValidationException $e) {
            return [
                'index' => $index,
                'ok' => false,
                'errors' => $e->errors(),
            ];
        }

        try {
            $id = $data['id'] ?? null;
            unset($data['id']);

            $workItem = $id
                ? tap(WorkItem::findOrFail($id))->update($data)
                : DB::transaction(fn () => WorkItem::create($data));

            return [
                'index' => $index,
                'ok' => true,
                'id' => $workItem->id,
                'number' => $workItem->number,
                'reference' => $workItem->reference(),
                'kind' => $workItem->kind,
                'name' => $workItem->name,
                'parent_id' => $workItem->parent_id,
                'created' => $workItem->wasRecentlyCreated,
            ];
        } catch (Throwable $e) {
            return [
                'index' => $index,
                'ok' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * @return array<string, string>
     */
    private function itemRules(): array
    {
        return [
            'id' => 'nullable|string|owned_work_item',
            'project_id' => 'required|string|owned_project',
            'parent_id' => 'nullable|string|owned_work_item',
            'responsible_role_id' => 'nullable|string|owned_role',
            'kind' => 'required|in:'.implode(',', WorkItem::KINDS),
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'needs_mockups' => 'sometimes|boolean',
            'status' => 'prohibited',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function itemMessages(): array
    {
        return [
            'status.prohibited' => 'Work item status is not set here. Use the work item transition tools (start, complete, block, unblock, cancel, reopen) to move status through validated transitions.',
        ];
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'items' => $schema->array()
                ->items($schema->object(fn (JsonSchema $s) => [
                    'id' => $s->string()->description('Existing work item ULID. Omit to create.'),
                    'project_id' => $s->string()->description('Project ULID')->required(),
                    'parent_id' => $s->string()->description('Parent work item ULID'),
                    'responsible_role_id' => $s->string()->description('Responsible role ULID'),
                    'kind' => $s->string()->description('Work item kind')->enum(WorkItem::KINDS)->required(),
                    'name' => $s->string()->description('Short label')->required(),
                    'description' => $s->string()->description('Optional details or acceptance notes'),
                    'needs_mockups' => $s->boolean()->description('Whether this work item requires one or more spec mockups before it is ready. Defaults to false.'),
                ]))
                ->min(1)
                ->max(100)
                ->description('Up to 100 work items to create or update. Items are committed independently; per-item failures are reported in the response without aborting the batch.')
                ->required(),
        ];
    }
}
