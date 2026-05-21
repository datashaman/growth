<?php

use App\Growth\Assurance\ReadinessGateEvaluator;
use App\Growth\Lint\BaselineLinter;
use App\Models\Project;
use App\Models\ProjectPlan;
use App\Models\ProjectPlanBaseline;
use App\Models\User;
use App\Models\WorkItem;

/**
 * `lint-project`'s `baselines` section and `evaluate-readiness-gates`'
 * `change_control` gate both source baseline drift from the same
 * {@see BaselineLinter}. This pins that they cannot disagree at one project
 * state — the divergence reported in feedback was a timing artifact between
 * two calls, not two surfaces computing drift differently.
 */
it('reports identical baseline drift through the linter and the readiness gate', function () {
    $user = User::factory()->create();
    $project = Project::create([
        'workspace_id' => $user->active_workspace_id,
        'name' => 'Drift',
        'rigor_level' => 2,
    ]);
    $plan = ProjectPlan::create(['project_id' => $project->id, 'status' => 'baselined']);
    $item = WorkItem::create([
        'project_id' => $project->id,
        'kind' => 'task',
        'name' => 'Renamed after baseline',
        'status' => 'todo',
    ]);

    // Baseline snapshot whose work-item name differs from the live row: an
    // uncovered scope-bearing change, so the differ flags baseline.drift.uncovered.
    ProjectPlanBaseline::create([
        'project_plan_id' => $plan->id,
        'version' => 1,
        'snapshot' => [
            'project_plan' => ['id' => $plan->id, 'project_id' => $project->id, 'status' => 'baselined'],
            'work_items' => [[
                'id' => $item->id,
                'parent_id' => null,
                'responsible_role_id' => null,
                'kind' => 'task',
                'name' => 'Original name',
                'status' => 'todo',
            ]],
        ],
        'baselined_at' => now(),
    ]);

    $lintFindings = app(BaselineLinter::class)->check($project->fresh());

    $changeControl = collect(app(ReadinessGateEvaluator::class)->evaluate($project->fresh())['gates'])
        ->firstWhere('id', 'change_control');

    $drift = fn (array $findings): array => collect($findings)
        ->where('rule', 'baseline.drift.uncovered')
        ->pluck('subject_id')
        ->sort()
        ->values()
        ->all();

    expect($drift($lintFindings))->toBe([$item->id])
        ->and($drift($changeControl['findings']))->toBe([$item->id])
        ->and($changeControl['status'])->toBe('fail');
});
