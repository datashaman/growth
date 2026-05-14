<?php

use App\Growth\Lint\TestLinter;
use App\Models\Project;
use App\Models\TestCase;
use App\Models\TestPlan;
use App\Models\User;

beforeEach(function () {
    $this->linter = app(TestLinter::class);
    $this->project = Project::create([
        'workspace_id' => User::factory()->create()->active_workspace_id,
        'name' => 'Verif',
        'rigor_level' => 2,
    ]);
});

function makePlan(Project $project, string $level, array $overrides = []): TestPlan
{
    return TestPlan::create(array_merge([
        'project_id' => $project->id,
        'level' => $level,
        'name' => ucfirst($level).' plan',
        'scope' => 'declared scope',
        'approach' => 'declared approach',
    ], $overrides));
}

it('does not flag plan-empty on a master plan with no cases', function () {
    $master = makePlan($this->project, 'master');
    makePlan($this->project, 'unit'); // subordinate present so master-no-subordinates does not fire

    $masterEmptyFindings = collect($this->linter->check($this->project->fresh()))
        ->where('rule', 'plan-empty')
        ->where('subject_id', $master->id);

    expect($masterEmptyFindings)->toBeEmpty();
});

it('flags plan-empty on a non-master plan with no cases', function () {
    $unit = makePlan($this->project, 'unit');
    makePlan($this->project, 'master');

    $findings = collect($this->linter->check($this->project->fresh()));
    $empty = $findings->firstWhere('rule', 'plan-empty');

    expect($empty)->not->toBeNull()
        ->and($empty['subject_id'])->toBe($unit->id)
        ->and($empty['message'])->toContain('verification plan')
        ->and($empty['message'])->not->toContain('test plan');
});

it('flags master-no-subordinates when the only plan is a master', function () {
    $master = makePlan($this->project, 'master');

    $finding = collect($this->linter->check($this->project->fresh()))
        ->firstWhere('rule', 'master-no-subordinates');

    expect($finding)->not->toBeNull()
        ->and($finding['severity'])->toBe('warning')
        ->and($finding['subject_id'])->toBe($master->id)
        ->and($finding['message'])->toContain("master verification plan [{$master->name}] has no subordinate plans");
});

it('does not flag master-no-subordinates when a subordinate plan exists', function () {
    makePlan($this->project, 'master');
    makePlan($this->project, 'integration');

    $rules = collect($this->linter->check($this->project->fresh()))->pluck('rule');

    expect($rules)->not->toContain('master-no-subordinates');
});

it('returns zero plan-empty or master-no-subordinates warnings for a master plan paired with one subordinate with cases', function () {
    makePlan($this->project, 'master');
    $unit = makePlan($this->project, 'unit');
    TestCase::create([
        'test_plan_id' => $unit->id,
        'name' => 'Smoke',
        'objective' => 'open app',
        'expected_results' => 'app opens',
    ]);

    $rules = collect($this->linter->check($this->project->fresh()))->pluck('rule');

    expect($rules)->not->toContain('plan-empty')
        ->and($rules)->not->toContain('master-no-subordinates');
});

it('uses "verification plan" terminology in plan-level rule messages', function () {
    $emptyProject = Project::create([
        'workspace_id' => User::factory()->create()->active_workspace_id,
        'name' => 'Empty',
        'rigor_level' => 2,
    ]);

    $messages = collect($this->linter->check($emptyProject))->pluck('message');

    expect($messages)->toContain('rule: project has no verification plans');
    expect($messages->filter(fn ($m) => str_contains($m, 'test plan'))->values()->all())->toBe([]);

    $noMasterProject = Project::create([
        'workspace_id' => User::factory()->create()->active_workspace_id,
        'name' => 'NoMaster',
        'rigor_level' => 2,
    ]);
    makePlan($noMasterProject, 'unit', ['scope' => null, 'approach' => null]);

    $messages = collect($this->linter->check($noMasterProject))->pluck('message');

    expect($messages)->toContain('rule: project has no master verification plan');
    expect($messages->filter(fn ($m) => str_contains($m, 'test plan'))->values()->all())->toBe([]);
});
