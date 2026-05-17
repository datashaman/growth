<?php

use App\Models\Agent;
use App\Models\Project;
use App\Models\ProjectPlan;
use App\Models\ProjectPlanBaseline;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;

beforeEach(function () {
    $this->alice = User::factory()->create();
    $this->bob = User::factory()->create();
    $this->aliceProject = Project::create([
        'workspace_id' => $this->alice->active_workspace_id,
        'name' => 'Apollo',
        'rigor_level' => 2,
    ]);
    $this->bobProject = Project::create([
        'workspace_id' => $this->bob->active_workspace_id,
        'name' => 'Bob',
        'rigor_level' => 2,
    ]);
});

it('stores the plan budget summary', function () {
    $plan = ProjectPlan::create([
        'project_id' => $this->aliceProject->id,
        'budget_summary' => '$250k capex, $40k monthly run.',
    ]);

    expect($plan->budget_summary)->toBe('$250k capex, $40k monthly run.');
});

it('relates baselines to plans, users, and agents', function () {
    $plan = ProjectPlan::create(['project_id' => $this->aliceProject->id]);
    $agent = Agent::create(['project_id' => $this->aliceProject->id, 'name' => 'pm-bot']);

    $baseline = ProjectPlanBaseline::create([
        'project_plan_id' => $plan->id,
        'version' => 1,
        'snapshot' => ['project_plan' => ['status' => 'draft']],
        'baselined_at' => '2026-05-11 10:00:00',
        'baselined_by_user_id' => $this->alice->id,
        'baselined_by_agent_id' => $agent->id,
        'note' => 'Initial baseline.',
    ]);

    expect($baseline->snapshot)->toBe(['project_plan' => ['status' => 'draft']]);
    expect($baseline->projectPlan->is($plan))->toBeTrue();
    expect($baseline->baselinedByUser->is($this->alice))->toBeTrue();
    expect($baseline->baselinedByAgent->is($agent))->toBeTrue();
    expect($plan->baselines()->count())->toBe(1);
});

it('enforces unique baseline versions per plan', function () {
    $plan = ProjectPlan::create(['project_id' => $this->aliceProject->id]);

    ProjectPlanBaseline::create([
        'project_plan_id' => $plan->id,
        'version' => 1,
        'snapshot' => [],
        'baselined_at' => now(),
    ]);

    expect(fn () => ProjectPlanBaseline::create([
        'project_plan_id' => $plan->id,
        'version' => 1,
        'snapshot' => [],
        'baselined_at' => now(),
    ]))->toThrow(UniqueConstraintViolationException::class);
});

it('scopes baselines through their parent plan owner', function () {
    $alicePlan = ProjectPlan::create(['project_id' => $this->aliceProject->id]);
    $bobPlan = ProjectPlan::create(['project_id' => $this->bobProject->id]);

    ProjectPlanBaseline::create([
        'project_plan_id' => $alicePlan->id,
        'version' => 1,
        'snapshot' => [],
        'baselined_at' => now(),
    ]);
    ProjectPlanBaseline::create([
        'project_plan_id' => $bobPlan->id,
        'version' => 1,
        'snapshot' => [],
        'baselined_at' => now(),
    ]);

    auth()->login($this->alice);

    expect(ProjectPlanBaseline::count())->toBe(1);
    expect(ProjectPlanBaseline::first()->project_plan_id)->toBe($alicePlan->id);
});
