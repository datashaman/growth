<?php

use App\Models\Project;
use App\Models\User;
use App\Models\WorkItem;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->project = Project::create([
        'user_id' => $this->user->id,
        'name' => 'Lunar Lander',
        'rigor_level' => 2,
    ]);
});

test('owner can create a work item from the create page', function () {
    $role = $this->project->roles()->create(['name' => 'Lead Engineer']);

    $this->actingAs($this->user);
    $this->get('/work-items/create?project='.$this->project->id)->assertOk();

    Livewire::test('pages::work-items.create', ['project' => $this->project->id])
        ->set('name', 'Wire descent engine')
        ->set('kind', 'task')
        ->set('status', 'in_progress')
        ->set('responsible_role_id', $role->id)
        ->set('description', 'Hook up ignition harness.')
        ->set('due_date', '2026-09-30')
        ->set('effort_estimate_hours', '40')
        ->call('save')
        ->assertHasNoErrors();

    $item = WorkItem::query()->where('name', 'Wire descent engine')->first();
    expect($item)->not->toBeNull()
        ->and($item->project_id)->toBe($this->project->id)
        ->and($item->responsible_role_id)->toBe($role->id)
        ->and((float) $item->effort_estimate_hours)->toBe(40.0);
});

test('work item create page 404s when project does not belong to user', function () {
    $bob = User::factory()->create();
    $bobProject = Project::create([
        'user_id' => $bob->id,
        'name' => 'Hostile',
        'rigor_level' => 1,
    ]);

    $this->actingAs($this->user)
        ->get('/work-items/create?project='.$bobProject->id)
        ->assertNotFound();
});

test('work item create requires name', function () {
    $this->actingAs($this->user);
    $this->get('/work-items/create?project='.$this->project->id);

    Livewire::test('pages::work-items.create', ['project' => $this->project->id])
        ->set('name', '')
        ->call('save')
        ->assertHasErrors(['name' => 'required']);
});

test('work item create rejects foreign responsible_role_id', function () {
    $otherUser = User::factory()->create();
    $otherProject = Project::create([
        'user_id' => $otherUser->id,
        'name' => 'Other',
        'rigor_level' => 1,
    ]);
    $foreignRole = $otherProject->roles()->create(['name' => 'Spy']);

    $this->actingAs($this->user);
    $this->get('/work-items/create?project='.$this->project->id);

    Livewire::test('pages::work-items.create', ['project' => $this->project->id])
        ->set('name', 'X')
        ->set('responsible_role_id', $foreignRole->id)
        ->call('save')
        ->assertHasErrors(['responsible_role_id']);
});

test('owner can edit a work item', function () {
    $item = $this->project->workItems()->create([
        'kind' => 'task',
        'name' => 'Original',
        'status' => 'todo',
    ]);

    $this->actingAs($this->user);
    $this->get('/work-items/'.$item->id.'/edit')->assertOk()->assertSee('Original');

    Livewire::test('pages::work-items.edit', ['workItem' => $item])
        ->set('name', 'Renamed')
        ->set('status', 'done')
        ->call('save')
        ->assertHasNoErrors();

    $item->refresh();
    expect($item->name)->toBe('Renamed')
        ->and($item->status)->toBe('done');
});

test('work item edit page 404s for another owner', function () {
    $bob = User::factory()->create();
    $bobProject = Project::create([
        'user_id' => $bob->id,
        'name' => 'Other',
        'rigor_level' => 1,
    ]);
    $bobItem = $bobProject->workItems()->create([
        'kind' => 'task',
        'name' => 'Bob',
        'status' => 'todo',
    ]);

    $this->actingAs($this->user)
        ->get('/work-items/'.$bobItem->id.'/edit')
        ->assertNotFound();
});

test('owner can delete a work item', function () {
    $item = $this->project->workItems()->create([
        'kind' => 'task',
        'name' => 'Delete me',
        'status' => 'todo',
    ]);

    $this->actingAs($this->user);

    Livewire::test('pages::work-items.delete-modal', ['workItemId' => $item->id])
        ->call('delete');

    expect(WorkItem::find($item->id))->toBeNull();
});

test('work item delete modal 404s for another owner', function () {
    $bob = User::factory()->create();
    $bobProject = Project::create([
        'user_id' => $bob->id,
        'name' => 'Other',
        'rigor_level' => 1,
    ]);
    $bobItem = $bobProject->workItems()->create([
        'kind' => 'task',
        'name' => 'Bob',
        'status' => 'todo',
    ]);

    $this->actingAs($this->user);

    Livewire::test('pages::work-items.delete-modal', ['workItemId' => $bobItem->id])
        ->call('delete')
        ->assertStatus(404);

    expect(WorkItem::withoutGlobalScopes()->find($bobItem->id))->not->toBeNull();
});

test('plan page renders New work item button', function () {
    $this->actingAs($this->user)
        ->get('/plan?project='.$this->project->id)
        ->assertOk()
        ->assertSee('New work item');
});
