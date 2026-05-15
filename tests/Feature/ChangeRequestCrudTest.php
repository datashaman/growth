<?php

use App\Models\ChangeRequest;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->project = Project::create([
        'workspace_id' => $this->user->active_workspace_id,
        'name' => 'Lunar Lander',
        'rigor_level' => 2,
    ]);
});

test('owner can create a change request', function () {
    $this->actingAs($this->user);

    Livewire::test('pages::change-requests.create-modal', ['projectId' => $this->project->id])
        ->set('title', 'Switch to oxygen-rich combustion')
        ->set('category', 'design')
        ->set('priority', 'high')
        ->set('status', 'under_review')
        ->call('save')
        ->assertHasNoErrors();

    $cr = ChangeRequest::query()->where('title', 'Switch to oxygen-rich combustion')->first();
    expect($cr)->not->toBeNull()
        ->and($cr->project_id)->toBe($this->project->id)
        ->and($cr->status)->toBe('under_review');
});

test('create rejects unknown category', function () {
    $this->actingAs($this->user);

    Livewire::test('pages::change-requests.create-modal', ['projectId' => $this->project->id])
        ->set('title', 'Bad')
        ->set('category', 'bogus')
        ->call('save')
        ->assertHasErrors('category');
});

test('create rejects requester role from another project', function () {
    $bob = User::factory()->create();
    $bobProject = Project::create([
        'workspace_id' => $bob->active_workspace_id, 'name' => 'Other', 'rigor_level' => 1,
    ]);
    $foreignRole = Role::create([
        'project_id' => $bobProject->id, 'name' => 'Foreigner',
    ]);
    $this->actingAs($this->user);

    Livewire::test('pages::change-requests.create-modal', ['projectId' => $this->project->id])
        ->set('title', 'X')
        ->set('requester_role_id', $foreignRole->id)
        ->call('save')
        ->assertHasErrors('requester_role_id');
});

test('owner can edit a change request', function () {
    $cr = $this->project->changeRequests()->create([
        'title' => 'Initial',
        'category' => 'scope',
        'status' => 'proposed',
        'priority' => 'low',
    ]);
    $this->actingAs($this->user);

    Livewire::test('pages::change-requests.edit-modal')
        ->call('load', $cr->id)
        ->set('status', 'approved')
        ->set('decision', 'approved')
        ->set('decided_at', '2026-05-13')
        ->call('save')
        ->assertHasNoErrors();

    expect($cr->fresh())
        ->status->toBe('approved')
        ->decision->toBe('approved');
});

test('edit 404s for another owner', function () {
    $bob = User::factory()->create();
    $bobProject = Project::create([
        'workspace_id' => $bob->active_workspace_id, 'name' => 'Other', 'rigor_level' => 1,
    ]);
    $bobCr = $bobProject->changeRequests()->create([
        'title' => 'Bob CR', 'category' => 'scope',
        'status' => 'proposed', 'priority' => 'low',
    ]);
    $this->actingAs($this->user);

    Livewire::test('pages::change-requests.edit-modal')
        ->call('load', $bobCr->id)
        ->assertStatus(404);
});

test('owner can delete a change request', function () {
    $cr = $this->project->changeRequests()->create([
        'title' => 'Doomed', 'category' => 'scope',
        'status' => 'proposed', 'priority' => 'low',
    ]);
    $this->actingAs($this->user);

    Livewire::test('pages::change-requests.delete-modal')
        ->call('load', $cr->id)
        ->call('delete');

    expect(ChangeRequest::find($cr->id))->toBeNull();
});

test('owner can view a change request show page', function () {
    $cr = $this->project->changeRequests()->create([
        'title' => 'Switch to oxygen-rich combustion',
        'description' => 'Replace existing burner with O2-augmented variant.',
        'rationale' => 'Improves combustion stability under load.',
        'category' => 'design',
        'status' => 'under_review',
        'priority' => 'high',
    ]);

    $this->actingAs($this->user)
        ->get('/change-requests/'.$cr->id)
        ->assertOk()
        ->assertSee('Switch to oxygen-rich combustion')
        ->assertSee('Replace existing burner with O2-augmented variant.')
        ->assertSee('Improves combustion stability under load.')
        ->assertSee('under review');
});

test('changes index links each title to the show page', function () {
    $cr = $this->project->changeRequests()->create([
        'title' => 'Initial', 'category' => 'scope',
        'status' => 'proposed', 'priority' => 'low',
    ]);

    $this->actingAs($this->user)
        ->get('/changes?project='.$this->project->id)
        ->assertOk()
        ->assertSee(route('change-requests.show', $cr), false);
});

test('show page 404s for a change request in another project', function () {
    $bob = User::factory()->create();
    $bobProject = Project::create([
        'workspace_id' => $bob->active_workspace_id, 'name' => 'Other', 'rigor_level' => 1,
    ]);
    $bobCr = $bobProject->changeRequests()->create([
        'title' => 'Bob CR', 'category' => 'scope',
        'status' => 'proposed', 'priority' => 'low',
    ]);

    $this->actingAs($this->user)
        ->get('/change-requests/'.$bobCr->id)
        ->assertNotFound();
});

test('owner can delete a change request from the show page and is redirected', function () {
    $cr = $this->project->changeRequests()->create([
        'title' => 'Doomed', 'category' => 'scope',
        'status' => 'proposed', 'priority' => 'low',
    ]);
    $this->actingAs($this->user);

    Livewire::test('pages::change-requests.delete-modal', [
        'changeRequestId' => $cr->id,
        'redirectAfter' => 'changes',
    ])
        ->call('delete')
        ->assertRedirect(route('changes'));

    expect(ChangeRequest::find($cr->id))->toBeNull();
});

test('changes page renders New change button', function () {
    $this->actingAs($this->user)
        ->get('/changes?project='.$this->project->id)
        ->assertOk()
        ->assertSee('New change');
});

test('changes page sidebar item is reachable', function () {
    $this->actingAs($this->user)
        ->get('/dashboard')
        ->assertOk()
        ->assertSee('Changes');
});
