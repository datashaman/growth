<?php

use App\Models\Project;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->alpha = Project::create([
        'workspace_id' => $this->user->active_workspace_id,
        'name' => 'Alpha',
        'rigor_level' => 1,
    ]);
    $this->beta = Project::create([
        'workspace_id' => $this->user->active_workspace_id,
        'name' => 'Beta',
        'rigor_level' => 1,
    ]);
});

test('switcher writes selection to the session', function () {
    $this->actingAs($this->user);

    Livewire::test('project-switcher')
        ->set('selectedProjectId', $this->beta->id);

    expect(session('selected_project_id'))->toBe($this->beta->id);
});

test('switcher redirects after a selection', function () {
    $this->actingAs($this->user);

    Livewire::test('project-switcher')
        ->set('selectedProjectId', $this->beta->id)
        ->assertRedirect();
});

test('switcher mounts with the session value if present', function () {
    $this->actingAs($this->user);
    session(['selected_project_id' => $this->beta->id]);

    Livewire::test('project-switcher')
        ->assertSet('selectedProjectId', $this->beta->id);
});

test('switcher falls back to first project when nothing in session', function () {
    $this->actingAs($this->user);

    Livewire::test('project-switcher')
        ->assertSet('selectedProjectId', $this->alpha->id);
});

test('switcher lists projects alphabetically', function () {
    Project::create([
        'workspace_id' => $this->user->active_workspace_id,
        'name' => 'aardvark',
        'rigor_level' => 1,
    ]);

    $this->actingAs($this->user);

    Livewire::test('project-switcher')
        ->assertSeeInOrder(['aardvark', 'Alpha', 'Beta']);
});

test('project scoped pages read the session selection', function () {
    $this->actingAs($this->user);
    session(['selected_project_id' => $this->beta->id]);

    $response = $this->get('/plan');
    $response->assertOk();
    $response->assertSee('Beta');
});

test('query string project still wins over session', function () {
    $this->actingAs($this->user);
    session(['selected_project_id' => $this->beta->id]);

    $response = $this->get('/plan?project='.$this->alpha->id);
    $response->assertOk();
    $response->assertSee('Alpha');
});
