<?php

use App\Models\Project;
use App\Models\User;
use App\Models\WorkItem;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    $this->project = Project::create([
        'workspace_id' => $this->user->active_workspace_id,
        'name' => 'Apollo Platform',
        'rigor_level' => 2,
    ]);
});

test('surfaces matching artifacts grouped by type when a query is typed', function () {
    WorkItem::create([
        'project_id' => $this->project->id,
        'kind' => 'task',
        'name' => 'Apollo launch checklist',
        'status' => 'todo',
    ]);

    Livewire::test('omni-search')
        ->set('query', 'apollo')
        ->assertSee('Apollo Platform')
        ->assertSee('Apollo launch checklist')
        ->assertSee('Work Items')
        ->assertSee('data-omni-index', false);
});

test('exposes the detail route for a hit', function () {
    $workItem = WorkItem::create([
        'project_id' => $this->project->id,
        'kind' => 'task',
        'name' => 'Orion handoff',
        'status' => 'todo',
    ]);

    Livewire::test('omni-search')
        ->set('query', 'orion')
        ->assertSee('/work-items/'.$workItem->id);
});

test('shows a prompt and no results for a query shorter than two characters', function () {
    Livewire::test('omni-search')
        ->set('query', 'a')
        ->assertSee('Type to search')
        ->assertDontSee('Apollo Platform');
});

test('shows no-match copy when nothing matches', function () {
    Livewire::test('omni-search')
        ->set('query', 'zzznomatch')
        ->assertSee('No matches.');
});

test('never surfaces another workspace\'s artifacts', function () {
    $other = User::factory()->create();
    Project::create([
        'workspace_id' => $other->active_workspace_id,
        'name' => 'Apollo secret',
        'rigor_level' => 2,
    ]);

    Livewire::test('omni-search')
        ->set('query', 'apollo')
        ->assertSee('Apollo Platform')
        ->assertDontSee('Apollo secret');
});
