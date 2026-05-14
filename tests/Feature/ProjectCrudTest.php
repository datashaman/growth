<?php

use App\Models\Project;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
});

test('owner can create a project', function () {
    $this->actingAs($this->user);

    Livewire::test('pages::projects.create-modal')
        ->set('name', 'Mars Rover')
        ->set('description', 'Surface operations.')
        ->set('rigor_level', 3)
        ->call('save')
        ->assertHasNoErrors();

    $project = Project::query()->where('name', 'Mars Rover')->first();
    expect($project)->not->toBeNull()
        ->and($project->user_id)->toBe($this->user->id)
        ->and($project->rigor_level)->toBe(3)
        ->and(session('selected_project_id'))->toBe($project->id);
});

test('create rejects out-of-range integrity level', function () {
    $this->actingAs($this->user);

    Livewire::test('pages::projects.create-modal')
        ->set('name', 'Bad')
        ->set('rigor_level', 9)
        ->call('save')
        ->assertHasErrors('rigor_level');
});

test('create requires name', function () {
    $this->actingAs($this->user);

    Livewire::test('pages::projects.create-modal')
        ->set('name', '')
        ->call('save')
        ->assertHasErrors('name');
});

test('owner can edit a project', function () {
    $project = Project::create([
        'user_id' => $this->user->id,
        'name' => 'Old',
        'rigor_level' => 1,
    ]);
    $this->actingAs($this->user);

    Livewire::test('pages::projects.edit-modal')
        ->call('load', $project->id)
        ->set('name', 'New')
        ->set('rigor_level', 4)
        ->call('save')
        ->assertHasNoErrors();

    expect($project->fresh())
        ->name->toBe('New')
        ->rigor_level->toBe(4);
});

test('project edit 404s for another owner', function () {
    $bob = User::factory()->create();
    $bobProject = Project::create([
        'user_id' => $bob->id, 'name' => 'Bob project', 'rigor_level' => 2,
    ]);
    $this->actingAs($this->user);

    Livewire::test('pages::projects.edit-modal')
        ->call('load', $bobProject->id)
        ->assertStatus(404);
});

test('owner can delete a project when confirmation matches', function () {
    $project = Project::create([
        'user_id' => $this->user->id,
        'name' => 'Doomed',
        'rigor_level' => 2,
    ]);
    session(['selected_project_id' => $project->id]);
    $this->actingAs($this->user);

    Livewire::test('pages::projects.delete-modal')
        ->call('load', $project->id)
        ->set('confirmation', 'Doomed')
        ->call('delete')
        ->assertHasNoErrors();

    expect(Project::withoutGlobalScopes()->find($project->id))->toBeNull()
        ->and(session('selected_project_id'))->toBeNull();
});

test('delete rejects wrong confirmation text', function () {
    $project = Project::create([
        'user_id' => $this->user->id,
        'name' => 'Keep me',
        'rigor_level' => 2,
    ]);
    $this->actingAs($this->user);

    Livewire::test('pages::projects.delete-modal')
        ->call('load', $project->id)
        ->set('confirmation', 'wrong')
        ->call('delete')
        ->assertHasErrors('confirmation');

    expect(Project::find($project->id))->not->toBeNull();
});

test('project delete 404s for another owner', function () {
    $bob = User::factory()->create();
    $bobProject = Project::create([
        'user_id' => $bob->id, 'name' => 'Bob project', 'rigor_level' => 2,
    ]);
    $this->actingAs($this->user);

    Livewire::test('pages::projects.delete-modal')
        ->call('load', $bobProject->id)
        ->assertStatus(404);
});

test('delete surfaces dependent counts', function () {
    $project = Project::create([
        'user_id' => $this->user->id,
        'name' => 'Busy',
        'rigor_level' => 2,
    ]);
    $project->stakeholders()->create(['name' => 'Alice', 'role' => 'sponsor']);
    $project->releases()->create(['version' => '1.0']);
    $this->actingAs($this->user);

    Livewire::test('pages::projects.delete-modal')
        ->call('load', $project->id)
        ->assertSet('counts.stakeholders', 1)
        ->assertSet('counts.releases', 1);
});

test('empty dashboard renders the New project button', function () {
    $this->actingAs($this->user)
        ->get('/dashboard')
        ->assertOk()
        ->assertSee('No projects yet')
        ->assertSee('New project');
});
