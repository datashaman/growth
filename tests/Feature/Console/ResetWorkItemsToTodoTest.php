<?php

use App\Models\Project;
use App\Models\StatusTransition;
use App\Models\User;
use App\Models\WorkItem;

it('resets listed in-progress work items to todo with audit rows', function () {
    $user = User::factory()->create(['email' => 'operator@example.com']);
    $project = Project::create([
        'workspace_id' => $user->active_workspace_id,
        'name' => 'UCampaign Platform',
        'rigor_level' => 2,
    ]);

    $first = WorkItem::create([
        'project_id' => $project->id,
        'kind' => 'task',
        'name' => 'First',
        'status' => 'in_progress',
    ]);
    $second = WorkItem::create([
        'project_id' => $project->id,
        'kind' => 'task',
        'name' => 'Second',
        'status' => 'in_progress',
    ]);

    $this->artisan('work-items:reset-to-todo', [
        'project' => $project->id,
        'items' => [$first->id, $second->id],
        '--actor' => 'operator@example.com',
        '--reason' => 'Phantom block correction.',
    ])
        ->expectsOutputToContain('Reset 2 work item(s) to todo in UCampaign Platform.')
        ->assertExitCode(0);

    expect($first->fresh()->status)->toBe('todo')
        ->and($second->fresh()->status)->toBe('todo')
        ->and(StatusTransition::count())->toBe(2);

    StatusTransition::query()->get()->each(function (StatusTransition $transition) use ($user): void {
        expect($transition->from_status)->toBe('in_progress')
            ->and($transition->to_status)->toBe('todo')
            ->and($transition->reason)->toBe('Phantom block correction.')
            ->and($transition->transitioned_by_user_id)->toBe($user->id);
    });
});

it('rejects the batch when any listed item is outside the project', function () {
    $user = User::factory()->create();
    $project = Project::create([
        'workspace_id' => $user->active_workspace_id,
        'name' => 'UCampaign Platform',
        'rigor_level' => 2,
    ]);
    $otherProject = Project::create([
        'workspace_id' => $user->active_workspace_id,
        'name' => 'Other',
        'rigor_level' => 2,
    ]);
    $item = WorkItem::create([
        'project_id' => $project->id,
        'kind' => 'task',
        'name' => 'First',
        'status' => 'in_progress',
    ]);
    $other = WorkItem::create([
        'project_id' => $otherProject->id,
        'kind' => 'task',
        'name' => 'Other',
        'status' => 'in_progress',
    ]);

    $this->artisan('work-items:reset-to-todo', [
        'project' => $project->id,
        'items' => [$item->id, $other->id],
    ])
        ->expectsOutputToContain('Some work items were not found in the project')
        ->assertExitCode(1);

    expect($item->fresh()->status)->toBe('in_progress')
        ->and($other->fresh()->status)->toBe('in_progress')
        ->and(StatusTransition::count())->toBe(0);
});

it('rejects the batch when any listed item is not in progress', function () {
    $user = User::factory()->create();
    $project = Project::create([
        'workspace_id' => $user->active_workspace_id,
        'name' => 'UCampaign Platform',
        'rigor_level' => 2,
    ]);
    $inProgress = WorkItem::create([
        'project_id' => $project->id,
        'kind' => 'task',
        'name' => 'First',
        'status' => 'in_progress',
    ]);
    $todo = WorkItem::create([
        'project_id' => $project->id,
        'kind' => 'task',
        'name' => 'Todo',
        'status' => 'todo',
    ]);

    $this->artisan('work-items:reset-to-todo', [
        'project' => $project->id,
        'items' => [$inProgress->id, $todo->id],
    ])
        ->expectsOutputToContain('All targeted work items must currently be in_progress')
        ->assertExitCode(1);

    expect($inProgress->fresh()->status)->toBe('in_progress')
        ->and($todo->fresh()->status)->toBe('todo')
        ->and(StatusTransition::count())->toBe(0);
});

it('rejects an actor outside the project workspace', function () {
    $owner = User::factory()->create();
    $outsider = User::factory()->create(['email' => 'outsider@example.com']);
    $project = Project::create([
        'workspace_id' => $owner->active_workspace_id,
        'name' => 'UCampaign Platform',
        'rigor_level' => 2,
    ]);
    $item = WorkItem::create([
        'project_id' => $project->id,
        'kind' => 'task',
        'name' => 'First',
        'status' => 'in_progress',
    ]);

    $this->artisan('work-items:reset-to-todo', [
        'project' => $project->id,
        'items' => [$item->id],
        '--actor' => 'outsider@example.com',
    ])
        ->expectsOutputToContain("does not belong to the project's workspace")
        ->assertExitCode(1);

    expect($item->fresh()->status)->toBe('in_progress')
        ->and($outsider->exists)->toBeTrue()
        ->and(StatusTransition::count())->toBe(0);
});
