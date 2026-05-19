<?php

use App\Growth\Lint\AdoptionLinter;
use App\Models\DesignElement;
use App\Models\DesignView;
use App\Models\Project;
use App\Models\Requirement;
use App\Models\TestCase;
use App\Models\TestPlan;
use App\Models\User;
use App\Models\WorkItem;
use Illuminate\Support\Collection;

beforeEach(function () {
    $this->workspaceId = User::factory()->create()->active_workspace_id;

    $this->makeProject = fn (bool $adopted): Project => Project::create([
        'workspace_id' => $this->workspaceId,
        'name' => 'Adopted',
        'rigor_level' => 2,
        'adopted_at' => $adopted ? now() : null,
    ]);

    /**
     * @return Collection<int,array<string,mixed>>
     */
    $this->findingsFor = fn (Project $project): Collection => collect(app(AdoptionLinter::class)->check($project->fresh()));
});

it('emits no findings for a non-adopted project', function () {
    $project = ($this->makeProject)(false);
    Requirement::create(['project_id' => $project->id, 'doc' => 'srs', 'type' => 'functional', 'text' => 'The system shall do a thing.']);
    WorkItem::create(['project_id' => $project->id, 'kind' => 'task', 'name' => 'Orphan', 'status' => 'todo']);

    expect(($this->findingsFor)($project))->toBeEmpty();
});

it('flags a requirement with no linked work item', function () {
    $project = ($this->makeProject)(true);
    $requirement = Requirement::create(['project_id' => $project->id, 'doc' => 'srs', 'type' => 'functional', 'text' => 'The system shall do a thing.']);

    $findings = ($this->findingsFor)($project)->where('rule', 'adoption.requirement.no_work_item')->values();

    expect($findings)->toHaveCount(1)
        ->and($findings->first())->toMatchArray([
            'rule' => 'adoption.requirement.no_work_item',
            'severity' => 'informational',
            'subject_type' => 'requirement',
            'subject_id' => $requirement->id,
        ]);
});

it('does not flag a requirement that has a linked work item', function () {
    $project = ($this->makeProject)(true);
    $requirement = Requirement::create(['project_id' => $project->id, 'doc' => 'srs', 'type' => 'functional', 'text' => 'The system shall do a thing.']);
    $workItem = WorkItem::create(['project_id' => $project->id, 'kind' => 'task', 'name' => 'Build it', 'status' => 'todo']);
    $requirement->workItems()->attach($workItem->id);

    expect(($this->findingsFor)($project)->where('rule', 'adoption.requirement.no_work_item'))->toBeEmpty();
});

it('flags a work item with no linked requirement', function () {
    $project = ($this->makeProject)(true);
    $workItem = WorkItem::create(['project_id' => $project->id, 'kind' => 'task', 'name' => 'Orphan', 'status' => 'todo']);

    $findings = ($this->findingsFor)($project)->where('rule', 'adoption.work_item.no_requirement')->values();

    expect($findings)->toHaveCount(1)
        ->and($findings->first())->toMatchArray([
            'rule' => 'adoption.work_item.no_requirement',
            'severity' => 'informational',
            'subject_type' => 'work_item',
            'subject_id' => $workItem->id,
        ]);
});

it('does not flag a work item that has a linked requirement', function () {
    $project = ($this->makeProject)(true);
    $requirement = Requirement::create(['project_id' => $project->id, 'doc' => 'srs', 'type' => 'functional', 'text' => 'The system shall do a thing.']);
    $workItem = WorkItem::create(['project_id' => $project->id, 'kind' => 'task', 'name' => 'Build it', 'status' => 'todo']);
    $workItem->requirements()->attach($requirement->id);

    expect(($this->findingsFor)($project)->where('rule', 'adoption.work_item.no_requirement'))->toBeEmpty();
});

it('flags a requirement with no verification case', function () {
    $project = ($this->makeProject)(true);
    $requirement = Requirement::create(['project_id' => $project->id, 'doc' => 'srs', 'type' => 'functional', 'text' => 'The system shall do a thing.']);

    $findings = ($this->findingsFor)($project)->where('rule', 'adoption.requirement.no_verification')->values();

    expect($findings)->toHaveCount(1)
        ->and($findings->first())->toMatchArray([
            'rule' => 'adoption.requirement.no_verification',
            'severity' => 'informational',
            'subject_type' => 'requirement',
            'subject_id' => $requirement->id,
        ]);
});

it('does not flag a requirement that has a verification case', function () {
    $project = ($this->makeProject)(true);
    $requirement = Requirement::create(['project_id' => $project->id, 'doc' => 'srs', 'type' => 'functional', 'text' => 'The system shall do a thing.']);
    $plan = TestPlan::create(['project_id' => $project->id, 'level' => 'master', 'name' => 'Plan']);
    $case = TestCase::create(['test_plan_id' => $plan->id, 'name' => 'Verify the thing', 'expected_results' => 'The thing is verified.']);
    $requirement->testCases()->attach($case->id);

    expect(($this->findingsFor)($project)->where('rule', 'adoption.requirement.no_verification'))->toBeEmpty();
});

it('flags an adopted project with zero requirements', function () {
    $project = ($this->makeProject)(true);

    $findings = ($this->findingsFor)($project)->where('rule', 'adoption.project.no_requirements')->values();

    expect($findings)->toHaveCount(1)
        ->and($findings->first())->toMatchArray([
            'rule' => 'adoption.project.no_requirements',
            'severity' => 'informational',
            'subject_type' => 'project',
            'subject_id' => $project->id,
        ]);
});

it('does not flag a project that has requirements', function () {
    $project = ($this->makeProject)(true);
    Requirement::create(['project_id' => $project->id, 'doc' => 'srs', 'type' => 'functional', 'text' => 'The system shall do a thing.']);

    expect(($this->findingsFor)($project)->where('rule', 'adoption.project.no_requirements'))->toBeEmpty();
});

it('flags an adopted project with zero design elements', function () {
    $project = ($this->makeProject)(true);

    $findings = ($this->findingsFor)($project)->where('rule', 'adoption.project.no_architecture')->values();

    expect($findings)->toHaveCount(1)
        ->and($findings->first())->toMatchArray([
            'rule' => 'adoption.project.no_architecture',
            'severity' => 'informational',
            'subject_type' => 'project',
            'subject_id' => $project->id,
        ]);
});

it('does not flag a project that has a design element', function () {
    $project = ($this->makeProject)(true);
    $view = DesignView::create(['project_id' => $project->id, 'viewpoint' => 'logical', 'name' => 'Logical view']);
    DesignElement::create(['design_view_id' => $view->id, 'kind' => 'entity', 'name' => 'Service']);

    expect(($this->findingsFor)($project)->where('rule', 'adoption.project.no_architecture'))->toBeEmpty();
});

it('emits every finding with informational severity', function () {
    $project = ($this->makeProject)(true);
    Requirement::create(['project_id' => $project->id, 'doc' => 'srs', 'type' => 'functional', 'text' => 'The system shall do a thing.']);
    WorkItem::create(['project_id' => $project->id, 'kind' => 'task', 'name' => 'Orphan', 'status' => 'todo']);

    $findings = ($this->findingsFor)($project);

    expect($findings)->not->toBeEmpty()
        ->and($findings->pluck('severity')->unique()->all())->toBe(['informational']);
});
