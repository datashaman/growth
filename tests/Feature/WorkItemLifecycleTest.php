<?php

use App\Growth\Transitions\BlockWorkItem;
use App\Growth\Transitions\IllegalTransitionException;
use App\Models\Project;
use App\Models\StatusTransition;
use App\Models\User;
use App\Models\WorkItem;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->project = Project::create([
        'workspace_id' => $this->user->active_workspace_id,
        'name' => 'Lifecycle',
        'rigor_level' => 2,
    ]);

    $this->makeItem = fn (string $status): WorkItem => WorkItem::create([
        'project_id' => $this->project->id,
        'kind' => 'task',
        'name' => 'Item',
        'status' => $status,
    ]);

    $this->actingAs($this->user);
});

it('enforces the blocker reason in the transition layer', function () {
    $item = ($this->makeItem)('todo');

    expect(fn () => (new BlockWorkItem)->apply($item))
        ->toThrow(IllegalTransitionException::class, 'A reason is required to block a work item.');

    expect($item->fresh()->status)->toBe('todo')
        ->and(StatusTransition::count())->toBe(0);
});
