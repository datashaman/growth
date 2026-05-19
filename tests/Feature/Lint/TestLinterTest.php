<?php

use App\Growth\Lint\TestLinter;
use App\Models\EvidenceAsset;
use App\Models\Project;
use App\Models\Requirement;
use App\Models\TestCase;
use App\Models\TestPlan;
use App\Models\TestRun;
use App\Models\User;
use App\Models\WorkItem;
use App\Models\WorkItemDeliveryLink;

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
        ->and($empty['message'])->toContain('Verification plan')
        ->and($empty['message'])->not->toContain('test plan');
});

it('flags master-no-subordinates when the only plan is a master', function () {
    $master = makePlan($this->project, 'master');

    $finding = collect($this->linter->check($this->project->fresh()))
        ->firstWhere('rule', 'master-no-subordinates');

    expect($finding)->not->toBeNull()
        ->and($finding['severity'])->toBe('warning')
        ->and($finding['subject_id'])->toBe($master->id)
        ->and($finding['message'])->toContain('Master verification plan has no subordinate plans');
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

    expect($messages)->toContain('Project has no verification plans');
    expect($messages->filter(fn ($m) => str_contains($m, 'test plan'))->values()->all())->toBe([]);

    $noMasterProject = Project::create([
        'workspace_id' => User::factory()->create()->active_workspace_id,
        'name' => 'NoMaster',
        'rigor_level' => 2,
    ]);
    makePlan($noMasterProject, 'unit', ['scope' => null, 'approach' => null]);

    $messages = collect($this->linter->check($noMasterProject))->pluck('message');

    expect($messages)->toContain('Project has no master verification plan');
    expect($messages->filter(fn ($m) => str_contains($m, 'test plan'))->values()->all())->toBe([]);
});

function rigorProject(int $rigor): Project
{
    return Project::create([
        'workspace_id' => User::factory()->create()->active_workspace_id,
        'name' => 'Visual rigor '.$rigor,
        'rigor_level' => $rigor,
    ]);
}

function uiRequirement(Project $project, bool $rendersUi = true): Requirement
{
    return Requirement::create([
        'project_id' => $project->id,
        'doc' => 'srs',
        'type' => 'functional',
        'text' => 'The dashboard renders a chart for the user',
        'renders_ui' => $rendersUi,
    ]);
}

function runFor(Project $project, Requirement $requirement, string $status): TestRun
{
    $plan = makePlan($project, 'unit');
    $case = TestCase::create([
        'test_plan_id' => $plan->id,
        'name' => 'Chart renders '.uniqid(),
        'objective' => 'see the chart',
        'expected_results' => 'the chart is visible',
    ]);
    $case->requirements()->attach($requirement);

    return TestRun::create([
        'test_case_id' => $case->id,
        'status' => $status,
        'run_at' => now(),
    ]);
}

function attachEvidence(Project $project, TestRun $run): EvidenceAsset
{
    $workItem = WorkItem::create([
        'project_id' => $project->id,
        'kind' => 'task',
        'name' => 'Build the chart',
    ]);
    $link = WorkItemDeliveryLink::create([
        'work_item_id' => $workItem->id,
        'type' => 'evidence',
        'ref' => '#1',
    ]);
    $asset = EvidenceAsset::create([
        'work_item_delivery_link_id' => $link->id,
        'path' => 'docs/evidence/chart.png',
        'caption' => 'chart.png',
    ]);
    $run->evidenceAssets()->attach($asset);

    return $asset;
}

function visualEvidenceFinding(TestLinter $linter, Project $project): ?array
{
    return collect($linter->check($project->fresh()))
        ->firstWhere('rule', 'ui-requirement-no-visual-evidence');
}

it('flags a UI-bearing requirement whose passing run carries no visual evidence', function () {
    $project = rigorProject(3);
    $requirement = uiRequirement($project);
    runFor($project, $requirement, 'pass');

    $finding = visualEvidenceFinding($this->linter, $project);

    expect($finding)->not->toBeNull()
        ->and($finding['severity'])->toBe('warning')
        ->and($finding['subject_type'])->toBe('requirement')
        ->and($finding['subject_id'])->toBe($requirement->id);
});

it('does not flag the visual-evidence rule below rigor level 3', function () {
    $project = rigorProject(2);
    $requirement = uiRequirement($project);
    runFor($project, $requirement, 'pass');

    expect(visualEvidenceFinding($this->linter, $project))->toBeNull();
});

it('does not flag when a passing run carries visual evidence', function () {
    $project = rigorProject(3);
    $requirement = uiRequirement($project);
    $run = runFor($project, $requirement, 'pass');
    attachEvidence($project, $run);

    expect(visualEvidenceFinding($this->linter, $project))->toBeNull();
});

it('does not flag when evidence is on one of several passing runs', function () {
    $project = rigorProject(3);
    $requirement = uiRequirement($project);
    runFor($project, $requirement, 'pass');
    $withEvidence = runFor($project, $requirement, 'pass');
    attachEvidence($project, $withEvidence);

    expect(visualEvidenceFinding($this->linter, $project))->toBeNull();
});

it('does not flag a requirement that does not render UI', function () {
    $project = rigorProject(3);
    $requirement = uiRequirement($project, rendersUi: false);
    runFor($project, $requirement, 'pass');

    expect(visualEvidenceFinding($this->linter, $project))->toBeNull();
});

it('does not flag when the requirement has only failing runs', function () {
    $project = rigorProject(3);
    $requirement = uiRequirement($project);
    runFor($project, $requirement, 'fail');

    expect(visualEvidenceFinding($this->linter, $project))->toBeNull();
});

it('does not flag a UI-bearing requirement with no verification runs', function () {
    $project = rigorProject(3);
    uiRequirement($project);

    expect(visualEvidenceFinding($this->linter, $project))->toBeNull();
});
