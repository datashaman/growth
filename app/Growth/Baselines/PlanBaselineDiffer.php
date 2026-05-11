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

        $workItemDeltas = $this->diffCollection(
            'work_item',
            (array) data_get($snapshot, 'work_items', []),
            $plan->project->workItems->map(fn (WorkItem $workItem) => $this->workItemState($workItem))->all(),
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
     * @return list<array<string,mixed>>
     */
    private function diffCollection(string $type, array $beforeRows, array $afterRows): array
    {
        $before = collect($beforeRows)->keyBy('id');
        $after = collect($afterRows)->keyBy('id');
        $deltas = [];

        foreach ($after as $id => $row) {
            if (! $before->has($id)) {
                $fieldChanges = $this->fieldChanges([], (array) $row);
                $deltas[] = [
                    'artifact_type' => $type,
                    'artifact_id' => $id,
                    'change_type' => 'added',
                    'changed_fields' => array_column($fieldChanges, 'field'),
                    'field_changes' => $fieldChanges,
                ];

                continue;
            }

            $fieldChanges = $this->fieldChanges((array) $before->get($id), (array) $row);
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
                $fieldChanges = $this->fieldChanges((array) $row, []);
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
     * @return list<string>
     */
    private function fieldChanges(array $before, array $after, array $ignored = []): array
    {
        $fields = array_values(array_diff(
            array_unique(array_merge(array_keys($before), array_keys($after))),
            $ignored,
        ));
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
            'planned_start_date' => $workItem->planned_start_date?->toDateString(),
            'due_date' => $workItem->due_date?->toDateString(),
            'effort_estimate' => $workItem->effort_estimate,
            'effort_estimate_hours' => $workItem->effort_estimate_hours,
            'effort_actual' => $workItem->effort_actual,
            'effort_actual_hours' => $workItem->effort_actual_hours,
            'cost_estimate' => $workItem->cost_estimate,
            'cost_estimate_amount' => $workItem->cost_estimate_amount,
            'cost_actual' => $workItem->cost_actual,
            'cost_actual_amount' => $workItem->cost_actual_amount,
            'cost_currency' => $workItem->cost_currency,
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
