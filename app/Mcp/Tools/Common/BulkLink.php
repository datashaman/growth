<?php

namespace App\Mcp\Tools\Common;

use App\Models\DesignView;
use App\Models\Milestone;
use App\Models\WorkItem;
use Illuminate\Contracts\JsonSchema\JsonSchema;
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
#[Description('Attach existing artifacts to each other in bulk: up to 100 (link_type, from_id, to_ids[]) tuples in one call. Each tuple is applied independently with syncWithoutDetaching — per-tuple validation or runtime failures are reported alongside successes without aborting the batch and without rolling back already-applied links. Supported link_type values: work_item_to_requirements (requirements are requirements, so this also covers requirement links), work_item_to_milestones, concerns_to_view.')]
class BulkLink extends Tool
{
    private const LINK_TYPES = [
        'work_item_to_requirements',
        'work_item_to_milestones',
        'concerns_to_view',
    ];

    public function handle(Request $request): ResponseFactory
    {
        $payload = $request->validate([
            'items' => 'required|array|min:1|max:100',
        ], [
            'items.max' => 'Batches are capped at 100 items per call. Split into smaller batches.',
        ]);

        $results = [];
        foreach ($payload['items'] as $index => $item) {
            $results[] = $this->linkItem((int) $index, is_array($item) ? $item : []);
        }

        return Response::structured(['items' => $results]);
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function linkItem(int $index, array $item): array
    {
        try {
            $base = Validator::make($item, [
                'link_type' => 'required|in:'.implode(',', self::LINK_TYPES),
                'from_id' => 'required|string',
                'to_ids' => 'required|array|min:1',
                'to_ids.*' => 'string',
            ])->validate();
        } catch (ValidationException $e) {
            return [
                'index' => $index,
                'ok' => false,
                'errors' => $e->errors(),
            ];
        }

        try {
            $detail = match ($base['link_type']) {
                'work_item_to_requirements' => $this->linkWorkItemToRequirements($base['from_id'], $base['to_ids']),
                'work_item_to_milestones' => $this->linkWorkItemToMilestones($base['from_id'], $base['to_ids']),
                'concerns_to_view' => $this->linkConcernsToView($base['from_id'], $base['to_ids']),
            };

            return [
                'index' => $index,
                'ok' => true,
                'link_type' => $base['link_type'],
                'from_id' => $base['from_id'],
            ] + $detail;
        } catch (ValidationException $e) {
            return [
                'index' => $index,
                'ok' => false,
                'errors' => $e->errors(),
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
     * @param  array<int, string>  $requirementIds
     * @return array<string, int>
     */
    private function linkWorkItemToRequirements(string $workItemId, array $requirementIds): array
    {
        Validator::make([
            'from_id' => $workItemId,
            'to_ids' => $requirementIds,
        ], [
            'from_id' => 'required|string|owned_work_item',
            'to_ids.*' => 'required|string|owned_requirement',
        ])->validate();

        $item = WorkItem::findOrFail($workItemId);
        $result = $item->requirements()->syncWithoutDetaching($requirementIds);

        return [
            'attached' => count($result['attached']),
            'unchanged' => count($requirementIds) - count($result['attached']),
        ];
    }

    /**
     * @param  array<int, string>  $milestoneIds
     * @return array<string, int>
     */
    private function linkWorkItemToMilestones(string $workItemId, array $milestoneIds): array
    {
        Validator::make([
            'from_id' => $workItemId,
            'to_ids' => $milestoneIds,
        ], [
            'from_id' => 'required|string|owned_work_item',
            'to_ids.*' => 'required|string|owned_milestone',
        ])->validate();

        $item = WorkItem::findOrFail($workItemId);

        // A milestone is a scope bundle within one project; reject the tuple
        // if any milestone belongs to a different project than the work item.
        if (Milestone::whereIn('id', $milestoneIds)->where('project_id', '!=', $item->project_id)->exists()) {
            throw ValidationException::withMessages([
                'to_ids' => 'A work item can only be linked to milestones in the same project.',
            ]);
        }

        $result = $item->milestones()->syncWithoutDetaching($milestoneIds);

        return [
            'attached' => count($result['attached']),
            'unchanged' => count($milestoneIds) - count($result['attached']),
        ];
    }

    /**
     * @param  array<int, string>  $concernIds
     * @return array<string, int>
     */
    private function linkConcernsToView(string $designViewId, array $concernIds): array
    {
        Validator::make([
            'from_id' => $designViewId,
            'to_ids' => $concernIds,
        ], [
            'from_id' => 'required|string|owned_design_view',
            'to_ids.*' => 'required|string|owned_concern',
        ])->validate();

        $view = DesignView::findOrFail($designViewId);
        $before = $view->concerns()->count();
        $view->concerns()->syncWithoutDetaching($concernIds);
        $after = $view->concerns()->count();

        return [
            'attached' => $after - $before,
            'unchanged' => count($concernIds) - ($after - $before),
        ];
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'items' => $schema->array()
                ->items($schema->object(fn (JsonSchema $s) => [
                    'link_type' => $s->string()
                        ->description('Which relationship to apply. work_item_to_requirements covers requirement links (requirements are requirements). work_item_to_milestones links a work item to one or more milestones. concerns_to_view attaches concerns to a design/architecture view; from_id is the view id and to_ids are concern ids.')
                        ->enum(self::LINK_TYPES)
                        ->required(),
                    'from_id' => $s->string()
                        ->description('Source ULID. For work_item_* this is a work item; for concerns_to_view this is a design view.')
                        ->required(),
                    'to_ids' => $s->array()
                        ->description('Target ULIDs to attach. Idempotent — existing links are kept.')
                        ->items($s->string())
                        ->min(1)
                        ->required(),
                ]))
                ->min(1)
                ->max(100)
                ->description('Up to 100 link tuples to apply. Each tuple uses syncWithoutDetaching, so existing links are preserved. Per-tuple failures are reported in the response without aborting the batch.')
                ->required(),
        ];
    }
}
