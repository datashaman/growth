<?php

namespace App\Mcp\Tools\Plan;

use App\Models\WorkItem;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Throwable;

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
                : WorkItem::create($data);

            return [
                'index' => $index,
                'ok' => true,
                'id' => $workItem->id,
                'kind' => $workItem->kind,
                'name' => $workItem->name,
                'parent_id' => $workItem->parent_id,
                'planned_start_date' => $workItem->planned_start_date?->toDateString(),
                'due_date' => $workItem->due_date?->toDateString(),
                'effort_estimate_hours' => $workItem->effort_estimate_hours,
                'effort_actual_hours' => $workItem->effort_actual_hours,
                'cost_estimate_amount' => $workItem->cost_estimate_amount,
                'cost_actual_amount' => $workItem->cost_actual_amount,
                'cost_currency' => $workItem->cost_currency,
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
            'status' => 'prohibited',
            'planned_start_date' => 'nullable|date_format:Y-m-d',
            'due_date' => 'nullable|date_format:Y-m-d',
            'effort_estimate' => 'nullable|string|max:60',
            'effort_estimate_hours' => 'nullable|numeric|min:0|max:999999',
            'effort_actual' => 'nullable|string|max:60',
            'effort_actual_hours' => 'nullable|numeric|min:0|max:999999',
            'cost_estimate' => 'nullable|string|max:60',
            'cost_estimate_amount' => 'nullable|numeric|min:0|max:9999999999',
            'cost_actual' => 'nullable|string|max:60',
            'cost_actual_amount' => 'nullable|numeric|min:0|max:9999999999',
            'cost_currency' => 'nullable|string|size:3',
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
                    'planned_start_date' => $s->string()->description('Planned start date in YYYY-MM-DD format'),
                    'due_date' => $s->string()->description('Due date in YYYY-MM-DD format'),
                    'effort_estimate' => $s->string()->description('Free-form effort estimate'),
                    'effort_estimate_hours' => $s->number()->description('Estimated effort in hours'),
                    'effort_actual' => $s->string()->description('Free-form actual effort'),
                    'effort_actual_hours' => $s->number()->description('Actual effort in hours'),
                    'cost_estimate' => $s->string()->description('Free-form cost estimate'),
                    'cost_estimate_amount' => $s->number()->description('Estimated cost amount'),
                    'cost_actual' => $s->string()->description('Free-form actual cost'),
                    'cost_actual_amount' => $s->number()->description('Actual cost amount'),
                    'cost_currency' => $s->string()->description('Three-letter currency code for numeric cost amounts, such as USD'),
                ]))
                ->min(1)
                ->max(100)
                ->description('Up to 100 work items to create or update. Items are committed independently; per-item failures are reported in the response without aborting the batch.')
                ->required(),
        ];
    }
}
