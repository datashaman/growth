<?php

use App\Growth\Baselines\PlanBaselineDiffer;
use App\Models\Project;
use App\Models\ProjectPlan;
use App\Models\ProjectPlanBaseline;
use App\Models\User;
use App\Models\WorkItem;

it('ignores retired fields carried by a legacy baseline snapshot', function () {
    $user = User::factory()->create();
    $project = Project::create([
        'workspace_id' => $user->active_workspace_id,
        'name' => 'Apollo',
        'rigor_level' => 2,
    ]);
    $plan = ProjectPlan::create(['project_id' => $project->id, 'status' => 'baselined']);
    $item = WorkItem::create([
        'project_id' => $project->id,
        'kind' => 'task',
        'name' => 'Wire the telemetry',
        'status' => 'todo',
    ]);

    // A snapshot taken before the schedule/effort/cost columns were retired:
    // the work-item row still carries those keys. The differ must compare only
    // the fields the current model defines, so a legacy snapshot whose live
    // fields are unchanged reports no drift.
    $baseline = ProjectPlanBaseline::create([
        'project_plan_id' => $plan->id,
        'version' => 1,
        'snapshot' => [
            'project_plan' => ['id' => $plan->id, 'project_id' => $project->id, 'status' => 'baselined'],
            'work_items' => [[
                'id' => $item->id,
                'parent_id' => null,
                'responsible_role_id' => null,
                'kind' => 'task',
                'name' => 'Wire the telemetry',
                'status' => 'todo',
                'planned_start_date' => '2026-01-01',
                'due_date' => '2026-02-01',
                'effort_estimate_hours' => 16,
                'cost_estimate_amount' => 2400,
                'cost_currency' => 'USD',
            ]],
        ],
        'baselined_at' => now(),
    ]);

    $diff = app(PlanBaselineDiffer::class)->diff($baseline);

    expect($diff['work_items'])->toBe([])
        ->and($diff['project_plan'])->toBe([])
        ->and($diff['summary'])->toMatchArray(['added' => 0, 'removed' => 0, 'changed' => 0]);
});
