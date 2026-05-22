<?php

/*
 * #375: the Plan and Dashboard work-item tables present items consistently —
 * both lead with the WI- reference and surface the responsible role — and the
 * UI explains the team-set Status vs the evidence-derived State.
 */

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

    $this->role = Role::create([
        'project_id' => $this->project->id,
        'name' => 'Descent Engineer',
    ]);

    $this->workItem = $this->project->workItems()->create([
        'kind' => 'task',
        'name' => 'Wire the descent engine',
        'status' => 'in_progress',
        'responsible_role_id' => $this->role->id,
    ]);

    session(['selected_project_id' => $this->project->id]);
});

test('the Plan work-items table leads with the WI- reference and the role', function () {
    Livewire::test('pages::plan')
        ->assertSee($this->workItem->reference())
        ->assertSee('Wire the descent engine')
        ->assertSee('Descent Engineer');
});

test('the Plan Status header explains the team-set vs evidence-derived distinction', function () {
    Livewire::test('pages::plan')
        ->assertSee('evidence-derived delivery State');
});

test('the Dashboard Implementation table leads with the WI- reference and the role', function () {
    $this->get('/dashboard?project='.$this->project->id)
        ->assertOk()
        ->assertSee($this->workItem->reference())
        ->assertSee('Wire the descent engine')
        ->assertSee('Descent Engineer');
});

test('the Dashboard Implementation headers explain Status vs State', function () {
    $this->get('/dashboard?project='.$this->project->id)
        ->assertOk()
        ->assertSee('Workflow status set by the team', false)
        ->assertSee('derived from evidence', false);
});
