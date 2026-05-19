<?php

use App\Models\Project;
use App\Models\Role;
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

it('shows assigned users in the roles table Users column', function () {
    $jennifer = User::factory()->create(['name' => 'Jennifer Walker']);
    $role = Role::create(['project_id' => $this->project->id, 'name' => 'Product Lead']);
    $role->users()->attach($jennifer);

    Livewire::test('pages::plan')
        ->assertSee('Product Lead')
        ->assertSee('Jennifer Walker');
});

it('renders the roles table for a role with no assigned users', function () {
    Role::create(['project_id' => $this->project->id, 'name' => 'Unfilled Role']);

    Livewire::test('pages::plan')
        ->assertOk()
        ->assertSee('Unfilled Role');
});
