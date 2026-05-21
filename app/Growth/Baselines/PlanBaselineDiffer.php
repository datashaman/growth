<?php

namespace App\Growth\Baselines;

use App\Models\ChangeRequest;
use App\Models\ProjectPlan;
use App\Models\ProjectPlanBaseline;
use App\Models\WorkItem;

class PlanBaselineDiffer
{
    /**
     * @return array{
     *     baseline_id:string,
     *     project_plan_id:string,
     *     version:int,
     *     summary:array{added:int,removed:int,changed:int,uncovered_changed:int},
     *     project_plan:list<array<string,mixed>>,
     *     work_items:list<array<string,mixed>>
     * }
     */
    public function diff(ProjectPlanBaseline $baseline): array
    {
        $plan = ProjectPlan::with('project.workItems')->findOrFail($baseline->project_plan_id);
        $snapshot = $baseline->snapshot ?? [];

        $planDeltas = $this->diffOne(
            'project_plan',
            (array) data_get($snapshot, 'project_plan', []),
            $this->planState($plan),
            $plan->id,
        );

        // `status` advancing (todo → done) is normal delivery lifecycle, not a
        // scope change against the baseline, so it is not drift — mirroring how
        // the plan diff already ignores `status`. Drift is reserved for the
        // scope-bearing fields (name, parent, responsible role, kind).
        $workItemDeltas = $this->diffCollection(
            'work_item',
            (array) data_get($snapshot, 'work_items', []),
            $plan->project->workItems->map(fn (WorkItem $workItem) => $this->workItemState($workItem))->all(),
            ignored: ['status'],
        );

        $covered = $this->coveredImpactKeys($plan->project_id);
        $planDeltas = $this->markCoverage($planDeltas, $covered);
        $workItemDeltas = $this->markCoverage($workItemDeltas, $covered);
        $all = array_merge($planDeltas, $workItemDeltas);

        return [
            'baseline_id' => $baseline->id,
            'project_plan_id' => $baseline->project_plan_id,
            'version' => $baseline->version,
            'summary' => [
                'added' => count(array_filter($all, fn ($delta) => $delta['change_type'] === 'added')),
                'removed' => count(array_filter($all, fn ($delta) => $delta['change_type'] === 'removed')),
                'changed' => count(array_filter($all, fn ($delta) => $delta['change_type'] === 'changed')),
                'uncovered_changed' => count(array_filter(
                    $all,
                    fn ($delta) => $delta['change_type'] !== 'removed' && ! $delta['covered_by_change']
                )),
            ],
            'project_plan' => $planDeltas,
            'work_items' => $workItemDeltas,
        ];
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function diffOne(string $type, array $before, array $after, string $id): array
    {
        $fieldChanges = $this->fieldChanges($before, $after, ignored: ['status']);

        if ($fieldChanges === []) {
            return [];
        }

        return [[
            'artifact_type' => $type,
            'artifact_id' => $id,
            'change_type' => 'changed',
            'changed_fields' => array_column($fieldChanges, 'field'),
            'field_changes' => $fieldChanges,
        ]];
    }

    /**
     * @param  list<array<string,mixed>>  $beforeRows
     * @param  list<array<string,mixed>>  $afterRows
     * @param  list<string>  $ignored  Fields whose change is not scope drift
     * @return list<array<string,mixed>>
     */
    private function diffCollection(string $type, array $beforeRows, array $afterRows, array $ignored = []): array
    {
        $before = collect($beforeRows)->keyBy('id');
        $after = collect($afterRows)->keyBy('id');
        $deltas = [];

        foreach ($after as $id => $row) {
            if (! $before->has($id)) {
                $fieldChanges = $this->fieldChanges([], (array) $row, $ignored);
                $deltas[] = [
                    'artifact_type' => $type,
                    'artifact_id' => $id,
                    'change_type' => 'added',
                    'changed_fields' => array_column($fieldChanges, 'field'),
                    'field_changes' => $fieldChanges,
                ];

                continue;
            }

            $fieldChanges = $this->fieldChanges((array) $before->get($id), (array) $row, $ignored);
            if ($fieldChanges !== []) {
                $deltas[] = [
                    'artifact_type' => $type,
                    'artifact_id' => $id,
                    'change_type' => 'changed',
                    'changed_fields' => array_column($fieldChanges, 'field'),
                    'field_changes' => $fieldChanges,
                ];
            }
        }

        foreach ($before as $id => $row) {
            if (! $after->has($id)) {
                $fieldChanges = $this->fieldChanges((array) $row, [], $ignored);
                $deltas[] = [
                    'artifact_type' => $type,
                    'artifact_id' => $id,
                    'change_type' => 'removed',
                    'changed_fields' => array_column($fieldChanges, 'field'),
                    'field_changes' => $fieldChanges,
                ];
            }
        }

        return $deltas;
    }

    /**
     * Compare two artifact states field by field.
     *
     * The field set is taken from the current state, not the union of both
     * sides. A baseline captured before a field was retired still carries that
     * key in its snapshot; comparing only the fields the current model defines
     * keeps a legacy snapshot from reporting spurious drift on a column that no
     * longer exists. For an added or removed artifact one side is empty, so the
     * non-empty side supplies the field set.
     *
     * @return list<array{field:string,before:mixed,after:mixed}>
     */
    private function fieldChanges(array $before, array $after, array $ignored = []): array
    {
        $canonical = $after !== [] ? array_keys($after) : array_keys($before);
        $fields = array_values(array_diff($canonical, $ignored));
        sort($fields);

        return array_values(array_filter(array_map(
            fn (string $field): ?array => ($before[$field] ?? null) === ($after[$field] ?? null)
                ? null
                : [
                    'field' => $field,
                    'before' => $before[$field] ?? null,
                    'after' => $after[$field] ?? null,
                ],
            $fields,
        )));
    }

    /**
     * @param  list<array<string,mixed>>  $deltas
     * @param  list<string>  $covered
     * @return list<array<string,mixed>>
     */
    private function markCoverage(array $deltas, array $covered): array
    {
        return array_map(function (array $delta) use ($covered): array {
            $delta['covered_by_change'] = in_array($delta['artifact_type'].':'.$delta['artifact_id'], $covered, true);

            return $delta;
        }, $deltas);
    }

    /**
     * @return array<string,mixed>
     */
    private function planState(ProjectPlan $plan): array
    {
        return [
            'id' => $plan->id,
            'project_id' => $plan->project_id,
            'status' => $plan->status,
            'scope_summary' => $plan->scope_summary,
            'objectives' => $plan->objectives,
            'deliverables_summary' => $plan->deliverables_summary,
            'approach' => $plan->approach,
            'organization_summary' => $plan->organization_summary,
            'assumptions' => $plan->assumptions,
            'constraints' => $plan->constraints,
            'budget_summary' => $plan->budget_summary,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function workItemState(WorkItem $workItem): array
    {
        return [
            'id' => $workItem->id,
            'parent_id' => $workItem->parent_id,
            'responsible_role_id' => $workItem->responsible_role_id,
            'kind' => $workItem->kind,
            'name' => $workItem->name,
            'status' => $workItem->status,
        ];
    }

    /**
     * @return list<string>
     */
    private function coveredImpactKeys(string $projectId): array
    {
        return ChangeRequest::query()
            ->where('project_id', $projectId)
            ->whereIn('status', ['approved', 'implemented'])
            ->where('decision', 'approved')
            ->with('impacts:id,change_request_id,impactable_type,impactable_id')
            ->get()
            ->flatMap(fn (ChangeRequest $change) => $change->impacts->map(
                fn ($impact) => $impact->impactable_type.':'.$impact->impactable_id
            ))
            ->unique()
            ->values()
            ->all();
    }
}
