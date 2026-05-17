<?php

use App\Growth\Lint\PmpLinter;
use App\Models\Project;
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
