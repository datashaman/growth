<?php

use App\Mcp\Servers\PlanningServer;
use App\Mcp\Tools\Plan\ActivatePlan;
use App\Mcp\Tools\Plan\BaselinePlan;
use App\Mcp\Tools\Plan\ClosePlan;
use App\Mcp\Tools\Plan\UpsertPlan;
use App\Models\Project;
use App\Models\ProjectPlan;
use App\Models\ProjectPlanBaseline;
use App\Models\StatusTransition;
use App\Models\User;
use Laravel\Passport\Passport;

beforeEach(function () {
    $this->user = User::factory()->create();
    Passport::actingAs($this->user, ['mcp:use']);

    $this->project = Project::create([
        'workspace_id' => $this->user->active_workspace_id,
        'name' => 'Apollo',
        'rigor_level' => 2,
    ]);

    $this->makePlan = fn (string $status): ProjectPlan => ProjectPlan::create([
        'project_id' => $this->project->id,
        'status' => $status,
    ]);
});

it('baselines a draft plan, snapshots it, and records a transition', function () {
    $plan = ($this->makePlan)('draft');

    PlanningServer::tool(BaselinePlan::class, ['project_plan_id' => $plan->id, 'note' => 'v1'])
        ->assertOk()
        ->assertStructuredContent(function ($json) {
            $json->where('version', 1)->etc();
        });

    expect($plan->fresh()->status)->toBe('baselined');

    $baseline = ProjectPlanBaseline::where('project_plan_id', $plan->id)->sole();
    expect($baseline->kind)->toBe('planned');

    $transition = StatusTransition::query()->sole();
    expect($transition->from_status)->toBe('draft')
        ->and($transition->to_status)->toBe('baselined')
        ->and($transition->transitionable->is($plan))->toBeTrue();
});

it('rejects baselining a plan that is not draft and writes no baseline', function () {
    $plan = ($this->makePlan)('active');

    PlanningServer::tool(BaselinePlan::class, ['project_plan_id' => $plan->id])
        ->assertHasErrors(['Cannot baseline a plan that is active.']);

    expect($plan->fresh()->status)->toBe('active');
    expect(ProjectPlanBaseline::where('project_plan_id', $plan->id)->count())->toBe(0);
    expect(StatusTransition::count())->toBe(0);
});

it('activates a baselined plan', function () {
    $plan = ($this->makePlan)('baselined');

    PlanningServer::tool(ActivatePlan::class, ['project_plan_id' => $plan->id])
        ->assertOk()
        ->assertStructuredContent(function ($json) {
            $json->where('from_status', 'baselined')->where('to_status', 'active')->etc();
        });

    expect($plan->fresh()->status)->toBe('active');
});

it('rejects activating a plan that is not baselined', function () {
    $plan = ($this->makePlan)('draft');

    PlanningServer::tool(ActivatePlan::class, ['project_plan_id' => $plan->id])
        ->assertHasErrors(['Cannot activate a plan that is draft.']);
});

it('closes an active plan', function () {
    $plan = ($this->makePlan)('active');

    PlanningServer::tool(ClosePlan::class, ['project_plan_id' => $plan->id])
        ->assertOk()
        ->assertStructuredContent(function ($json) {
            $json->where('to_status', 'closed')->etc();
        });

    expect($plan->fresh()->status)->toBe('closed');
});

it('rejects closing a plan that is not active', function () {
    $plan = ($this->makePlan)('baselined');

    PlanningServer::tool(ClosePlan::class, ['project_plan_id' => $plan->id])
        ->assertHasErrors(['Cannot close a plan that is baselined.']);
});

it('rejects status passed to upsert-plan with a pointer to the transition tools', function () {
    PlanningServer::tool(UpsertPlan::class, [
        'project_id' => $this->project->id,
        'status' => 'active',
    ])->assertHasErrors(['Plan status is not set here. Use the baseline-plan, activate-plan, and close-plan tools to move status through validated transitions.']);
});

it('rejects an empty status key on upsert-plan rather than persisting it', function () {
    PlanningServer::tool(UpsertPlan::class, [
        'project_id' => $this->project->id,
        'status' => null,
    ])->assertHasErrors(['Plan status is not set here. Use the baseline-plan, activate-plan, and close-plan tools to move status through validated transitions.']);

    expect(ProjectPlan::where('project_id', $this->project->id)->exists())->toBeFalse();
});
