<?php

use App\Growth\Plan\ScheduleHealthSummarizer;
use App\Models\Project;
use App\Models\User;
use App\Models\WorkItem;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->project = Project::create([
        'workspace_id' => $this->user->active_workspace_id,
        'name' => 'Pipeline',
        'rigor_level' => 2,
    ]);
});

function workItem(Project $project, string $name, string $status): WorkItem
{
    return WorkItem::create([
        'project_id' => $project->id,
        'kind' => WorkItem::KINDS[0],
        'name' => $name,
        'status' => $status,
    ]);
}

function openDependencyRules(Project $project): array
{
    $summary = (new ScheduleHealthSummarizer)->summarize($project);

    return array_values(array_filter(
        $summary['findings'],
        fn (array $finding): bool => $finding['rule'] === 'schedule.dependency.open',
    ));
}

test('an in-progress work item with an unfinished dependency is flagged', function () {
    $dependency = workItem($this->project, 'Event ledger', 'todo');
    $item = workItem($this->project, 'Aggregation pipeline', 'in_progress');
    $item->dependencies()->attach($dependency);

    $findings = openDependencyRules($this->project);

    expect($findings)->toHaveCount(1)
        ->and($findings[0]['subject_id'])->toBe($item->id)
        ->and($findings[0]['meta']['depends_on_id'])->toBe($dependency->id);
});

test('a todo work item with an unfinished dependency is not flagged', function () {
    $dependency = workItem($this->project, 'Event ledger', 'todo');
    $item = workItem($this->project, 'Aggregation pipeline', 'todo');
    $item->dependencies()->attach($dependency);

    expect(openDependencyRules($this->project))->toBeEmpty();
});

test('an in-progress work item whose dependency is done is not flagged', function () {
    $dependency = workItem($this->project, 'Event ledger', 'done');
    $item = workItem($this->project, 'Aggregation pipeline', 'in_progress');
    $item->dependencies()->attach($dependency);

    expect(openDependencyRules($this->project))->toBeEmpty();
});
