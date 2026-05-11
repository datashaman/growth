<?php

namespace App\Growth\Plan;

use App\Models\Project;
use App\Models\Role;

class PlanCapacitySummarizer
{
    /**
     * @return array<string,mixed>
     */
    public function summarize(Project $project): array
    {
        $roles = $project->roles()->orderBy('name')->get()->keyBy('id');
        $items = $project->workItems()->with('responsibleRole')->get();

        $rows = $roles->map(fn (Role $role): array => $this->emptyRoleRow($role))->all();
        $unassignedKey = 'unassigned';
        $rows[$unassignedKey] = [
            'role_id' => null,
            'role' => 'Unassigned',
            'weekly_capacity_hours' => null,
            'hourly_rate_amount' => null,
            'rate_currency' => null,
            'work_items' => 0,
            'effort_estimate_hours' => 0.0,
            'effort_actual_hours' => 0.0,
            'cost_estimate_amount' => 0.0,
            'cost_actual_amount' => 0.0,
            'utilization_estimate' => null,
        ];

        foreach ($items as $item) {
            $key = $item->responsible_role_id ?: $unassignedKey;
            $rows[$key] ??= $this->emptyRoleRow($item->responsibleRole);

            $estimateHours = (float) ($item->effort_estimate_hours ?? 0);
            $actualHours = (float) ($item->effort_actual_hours ?? 0);
            $rate = (float) ($item->responsibleRole?->hourly_rate_amount ?? 0);

            $rows[$key]['work_items']++;
            $rows[$key]['effort_estimate_hours'] += $estimateHours;
            $rows[$key]['effort_actual_hours'] += $actualHours;
            $rows[$key]['cost_estimate_amount'] += $item->cost_estimate_amount !== null
                ? (float) $item->cost_estimate_amount
                : $estimateHours * $rate;
            $rows[$key]['cost_actual_amount'] += $item->cost_actual_amount !== null
                ? (float) $item->cost_actual_amount
                : $actualHours * $rate;
        }

        $rows = collect($rows)
            ->map(function (array $row): array {
                $capacity = (float) ($row['weekly_capacity_hours'] ?? 0);
                $row['utilization_estimate'] = $capacity > 0
                    ? round($row['effort_estimate_hours'] / $capacity, 2)
                    : null;

                return $row;
            })
            ->values()
            ->all();

        return [
            'project_id' => $project->id,
            'totals' => [
                'weekly_capacity_hours' => array_sum(array_map(fn ($row) => (float) ($row['weekly_capacity_hours'] ?? 0), $rows)),
                'effort_estimate_hours' => array_sum(array_column($rows, 'effort_estimate_hours')),
                'effort_actual_hours' => array_sum(array_column($rows, 'effort_actual_hours')),
                'cost_estimate_amount' => array_sum(array_column($rows, 'cost_estimate_amount')),
                'cost_actual_amount' => array_sum(array_column($rows, 'cost_actual_amount')),
            ],
            'roles' => $rows,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function emptyRoleRow(?Role $role): array
    {
        return [
            'role_id' => $role?->id,
            'role' => $role?->name,
            'weekly_capacity_hours' => $role?->weekly_capacity_hours !== null ? (float) $role->weekly_capacity_hours : null,
            'hourly_rate_amount' => $role?->hourly_rate_amount !== null ? (float) $role->hourly_rate_amount : null,
            'rate_currency' => $role?->rate_currency,
            'work_items' => 0,
            'effort_estimate_hours' => 0.0,
            'effort_actual_hours' => 0.0,
            'cost_estimate_amount' => 0.0,
            'cost_actual_amount' => 0.0,
            'utilization_estimate' => null,
        ];
    }
}
