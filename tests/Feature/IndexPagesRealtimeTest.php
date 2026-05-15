<?php

use App\Models\Project;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    $this->project = Project::create([
        'workspace_id' => $this->user->active_workspace_id,
        'name' => 'Lunar Lander',
        'rigor_level' => 2,
    ]);

    session(['selected_project_id' => $this->project->id]);
});

test('changes page picks up newly-created change requests on broadcast', function () {
    $this->project->changeRequests()->create([
        'title' => 'Initial CR', 'category' => 'scope',
        'status' => 'proposed', 'priority' => 'low',
    ]);

    $component = Livewire::test('pages::changes')->assertSee('Initial CR');

    $this->project->changeRequests()->create([
        'title' => 'Newly broadcast CR', 'category' => 'scope',
        'status' => 'proposed', 'priority' => 'low',
    ]);

    $component
        ->call('onProjectDataChanged')
        ->assertSee('Newly broadcast CR');
});

test('plan page picks up newly-created work items on broadcast', function () {
    $this->project->workItems()->create([
        'name' => 'Existing task', 'kind' => 'task', 'status' => 'todo',
    ]);

    $component = Livewire::test('pages::plan')->assertSee('Existing task');

    $this->project->workItems()->create([
        'name' => 'Broadcast task', 'kind' => 'task', 'status' => 'todo',
    ]);

    $component
        ->call('onProjectDataChanged')
        ->assertSee('Broadcast task');
});

test('requirements index picks up newly-created requirements on broadcast', function () {
    $this->project->requirements()->create([
        'doc' => 'srs', 'type' => 'functional', 'text' => 'Existing requirement.',
    ]);

    $component = Livewire::test('pages::requirements.index')->assertSee('Existing requirement.');

    $this->project->requirements()->create([
        'doc' => 'srs', 'type' => 'functional', 'text' => 'Broadcast requirement.',
    ]);

    $component
        ->call('onProjectDataChanged')
        ->assertSee('Broadcast requirement.');
});

test('listener registration is skipped when no project is selected', function () {
    session()->forget('selected_project_id');

    $other = User::factory()->create();
    $this->actingAs($other);

    foreach (['pages::changes', 'pages::plan', 'pages::requirements.index'] as $component) {
        $listeners = Livewire::test($component)->instance()->getListeners();
        expect($listeners)->toBe([]);
    }
});
