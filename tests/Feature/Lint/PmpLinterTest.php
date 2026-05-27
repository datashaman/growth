<?php

use App\Growth\Assurance\ReadinessGateEvaluator;
use App\Growth\Lint\PmpLinter;
use App\Models\Project;
use App\Models\Requirement;
use App\Models\User;
use App\Models\WorkItem;
use Illuminate\Support\Collection;

beforeEach(function () {
    $this->project = Project::create([
        'workspace_id' => User::factory()->create()->active_workspace_id,
        'name' => 'Planning',
        'rigor_level' => 2,
    ]);

    /**
     * @return Collection<int,array<string,mixed>>
     */
    $this->dependencyOpenFindings = fn (): Collection => collect(app(PmpLinter::class)->check($this->project->fresh()))
        ->where('rule', 'pmp.dependency.open')
        ->values();

    $this->workItem = fn (string $status): WorkItem => WorkItem::create([
        'project_id' => $this->project->id,
        'kind' => 'task',
        'name' => 'Item '.$status,
        'status' => $status,
    ]);
});

it('flags a work item in progress while a dependency is unfinished', function () {
    $dependency = ($this->workItem)('todo');
    $item = ($this->workItem)('in_progress');
    $item->dependencies()->attach($dependency->id);

    $findings = ($this->dependencyOpenFindings)();

    expect($findings)->toHaveCount(1)
        ->and($findings->first())->toMatchArray([
            'rule' => 'pmp.dependency.open',
            'severity' => 'warning',
            'subject_type' => 'work_item',
            'subject_id' => $item->id,
        ]);
});

it('does not flag when the dependent item is not yet in progress', function () {
    $dependency = ($this->workItem)('todo');
    $item = ($this->workItem)('todo');
    $item->dependencies()->attach($dependency->id);

    expect(($this->dependencyOpenFindings)())->toBeEmpty();
});

it('does not flag when the dependency is already done', function () {
    $dependency = ($this->workItem)('done');
    $item = ($this->workItem)('in_progress');
    $item->dependencies()->attach($dependency->id);

    expect(($this->dependencyOpenFindings)())->toBeEmpty();
});

function uiNoMockupFindings(Project $project): Collection
{
    return collect(app(PmpLinter::class)->check($project->fresh()))
        ->where('rule', 'pmp.requirement.ui_no_mockup')
        ->values();
}

function mockupGapRequirement(Project $project, bool $rendersUi = true): Requirement
{
    return Requirement::create([
        'project_id' => $project->id,
        'doc' => 'srs',
        'type' => 'functional',
        'text' => 'The dashboard shall render a chart',
        'renders_ui' => $rendersUi,
    ]);
}

function coveringWorkItem(Project $project, bool $needsMockups): WorkItem
{
    return WorkItem::create([
        'project_id' => $project->id,
        'kind' => 'task',
        'name' => 'Build the screen',
        'needs_mockups' => $needsMockups,
    ]);
}

it('flags a renders_ui requirement whose covering work items all need no mockup', function () {
    $requirement = mockupGapRequirement($this->project);
    $requirement->workItems()->attach(coveringWorkItem($this->project, needsMockups: false));

    $findings = uiNoMockupFindings($this->project);

    expect($findings)->toHaveCount(1)
        ->and($findings->first())->toMatchArray([
            'rule' => 'pmp.requirement.ui_no_mockup',
            'severity' => 'informational',
            'subject_type' => 'requirement',
            'subject_id' => $requirement->id,
        ]);
});

it('does not flag when at least one covering work item needs a mockup', function () {
    $requirement = mockupGapRequirement($this->project);
    $requirement->workItems()->attach(coveringWorkItem($this->project, needsMockups: false));
    $requirement->workItems()->attach(coveringWorkItem($this->project, needsMockups: true));

    expect(uiNoMockupFindings($this->project))->toBeEmpty();
});

it('does not flag a renders_ui requirement with no covering work items', function () {
    mockupGapRequirement($this->project);

    expect(uiNoMockupFindings($this->project))->toBeEmpty();
});

it('does not flag a non-renders_ui requirement regardless of its work items', function () {
    $requirement = mockupGapRequirement($this->project, rendersUi: false);
    $requirement->workItems()->attach(coveringWorkItem($this->project, needsMockups: false));

    expect(uiNoMockupFindings($this->project))->toBeEmpty();
});

it('surfaces the ui_no_mockup finding in the planning gate without counting it', function () {
    $requirement = mockupGapRequirement($this->project);
    $requirement->workItems()->attach(coveringWorkItem($this->project, needsMockups: false));

    $gate = collect(app(ReadinessGateEvaluator::class)->evaluate($this->project->fresh())['gates'])
        ->firstWhere('id', 'planning');
    $findings = collect($gate['findings']);

    // The finding is reported, but informational severity is never tallied —
    // errors/warnings count only their own severities, so the finding cannot
    // move the gate.
    expect($findings->firstWhere('rule', 'pmp.requirement.ui_no_mockup')['severity'])->toBe('informational')
        ->and($gate['warnings'])->toBe($findings->where('severity', 'warning')->count())
        ->and($gate['errors'])->toBe($findings->where('severity', 'error')->count());
});

function noImplementationWorkFindings(Project $project): Collection
{
    return collect(app(PmpLinter::class)->check($project->fresh()))
        ->where('rule', 'pmp.wbs.no_implementation_work')
        ->values();
}

function softwareRequirement(Project $project): Requirement
{
    return Requirement::create([
        'project_id' => $project->id,
        'doc' => 'srs',
        'type' => 'functional',
        'text' => 'The platform shall let vendors manage orders.',
    ]);
}

it('flags a software project whose WBS is documentation only', function () {
    softwareRequirement($this->project);
    WorkItem::create([
        'project_id' => $this->project->id,
        'kind' => 'task',
        'name' => 'Write implementation runbook',
        'description' => 'Document the deployment checklist and support process.',
    ]);
    WorkItem::create([
        'project_id' => $this->project->id,
        'kind' => 'task',
        'name' => 'Define launch acceptance checklist',
        'description' => 'Capture go/no-go criteria for launch readiness.',
    ]);

    $findings = noImplementationWorkFindings($this->project);

    expect($findings)->toHaveCount(1)
        ->and($findings->first())->toMatchArray([
            'rule' => 'pmp.wbs.no_implementation_work',
            'severity' => 'error',
            'subject_type' => 'project',
            'subject_id' => $this->project->id,
        ]);
});

it('does not flag a software project with a code-producing implementation slice', function () {
    softwareRequirement($this->project);
    WorkItem::create([
        'project_id' => $this->project->id,
        'kind' => 'task',
        'name' => 'Write rollout notes',
        'description' => 'Document deployment steps.',
    ]);
    WorkItem::create([
        'project_id' => $this->project->id,
        'kind' => 'task',
        'name' => 'Implement vendor order dashboard',
        'description' => 'Build the Livewire page and persistence for vendor order management.',
    ]);

    expect(noImplementationWorkFindings($this->project))->toBeEmpty();
});

it('does not flag a software project with a requirement-linked delivery slice', function () {
    $requirement = softwareRequirement($this->project);
    $workItem = WorkItem::create([
        'project_id' => $this->project->id,
        'kind' => 'deliverable',
        'name' => 'Deliver primary action',
    ]);
    $workItem->requirements()->attach($requirement);

    expect(noImplementationWorkFindings($this->project))->toBeEmpty();
});

it('surfaces a documentation-only software WBS in the planning gate', function () {
    softwareRequirement($this->project);
    WorkItem::create([
        'project_id' => $this->project->id,
        'kind' => 'task',
        'name' => 'Prepare launch runbook',
        'description' => 'Document the launch checklist.',
    ]);

    $gate = collect(app(ReadinessGateEvaluator::class)->evaluate($this->project->fresh())['gates'])
        ->firstWhere('id', 'planning');
    $findings = collect($gate['findings']);

    expect($findings->firstWhere('rule', 'pmp.wbs.no_implementation_work'))->not->toBeNull()
        ->and($gate['status'])->toBe('fail');
});
