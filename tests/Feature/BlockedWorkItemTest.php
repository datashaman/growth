<?php

use App\Growth\Transitions\BlockWorkItem;
use App\Models\Project;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->project = Project::create([
        'workspace_id' => $this->user->active_workspace_id,
        'name' => 'Festival Market',
        'rigor_level' => 2,
    ]);
});

test('a blocked work item surfaces its recorded block reason', function () {
    $workItem = $this->project->workItems()->create([
        'kind' => 'task',
        'name' => 'Vendor dashboard shell',
        'status' => 'in_progress',
    ]);
    (new BlockWorkItem)->apply($workItem, $this->user, 'Waiting on the auth service contract.');

    $this->actingAs($this->user)
        ->get('/work-items/'.$workItem->id)
        ->assertOk()
        ->assertSee('Blocked')
        ->assertSee('Waiting on the auth service contract.');
});

test('a blocked work item lists unfinished upstream dependencies as blockers', function () {
    $upstream = $this->project->workItems()->create([
        'kind' => 'task',
        'name' => 'Auth service contract',
        'status' => 'in_progress',
    ]);
    $workItem = $this->project->workItems()->create([
        'kind' => 'task',
        'name' => 'Vendor dashboard shell',
        'status' => 'in_progress',
    ]);
    $workItem->dependencies()->attach($upstream);
    (new BlockWorkItem)->apply($workItem, $this->user, 'Blocked by upstream.');

    $this->actingAs($this->user)
        ->get('/work-items/'.$workItem->id)
        ->assertOk()
        ->assertSee('Waiting on unfinished upstream work')
        ->assertSee($upstream->reference().' — Auth service contract', false);
});

test('a non-blocked work item shows no blocked callout', function () {
    $workItem = $this->project->workItems()->create([
        'kind' => 'task',
        'name' => 'Vendor dashboard shell',
        'status' => 'in_progress',
    ]);

    $this->actingAs($this->user)
        ->get('/work-items/'.$workItem->id)
        ->assertOk()
        ->assertDontSee('This work item is blocked');
});
