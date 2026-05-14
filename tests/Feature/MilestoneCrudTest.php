<?php

use App\Models\Milestone;
use App\Models\Project;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->project = Project::create([
        'user_id' => $this->user->id,
        'name' => 'Lunar Lander',
        'rigor_level' => 2,
    ]);
});

test('owner can create a milestone', function () {
    $this->actingAs($this->user);

    Livewire::test('pages::milestones.create-modal', ['projectId' => $this->project->id])
        ->set('name', 'CDR')
        ->set('target_date', '2026-09-01')
        ->set('exit_criteria', 'Design closed.')
        ->set('status', 'pending')
        ->call('save')
        ->assertHasNoErrors();

    $milestone = Milestone::query()->where('name', 'CDR')->first();
    expect($milestone)->not->toBeNull()
        ->and($milestone->project_id)->toBe($this->project->id)
        ->and($milestone->target_date->format('Y-m-d'))->toBe('2026-09-01');
});

test('milestone create requires name', function () {
    $this->actingAs($this->user);

    Livewire::test('pages::milestones.create-modal', ['projectId' => $this->project->id])
        ->set('name', '')
        ->call('save')
        ->assertHasErrors(['name' => 'required']);
});

test('milestone create projectId is locked', function () {
    $bob = User::factory()->create();
    $bobProject = Project::create([
        'user_id' => $bob->id,
        'name' => 'Hostile',
        'rigor_level' => 1,
    ]);

    $this->actingAs($this->user);

    expect(fn () => Livewire::test('pages::milestones.create-modal', ['projectId' => $this->project->id])
        ->set('projectId', $bobProject->id))
        ->toThrow(Exception::class);
});

test('owner can edit a milestone via dispatched event', function () {
    $milestone = $this->project->milestones()->create([
        'name' => 'CDR',
        'status' => 'pending',
    ]);

    $this->actingAs($this->user);

    Livewire::test('pages::milestones.edit-modal')
        ->call('load', $milestone->id)
        ->set('name', 'Renamed CDR')
        ->set('status', 'hit')
        ->call('save')
        ->assertHasNoErrors();

    $milestone->refresh();
    expect($milestone->name)->toBe('Renamed CDR')
        ->and($milestone->status)->toBe('hit');
});

test('milestone edit 404s for another owner', function () {
    $bob = User::factory()->create();
    $bobProject = Project::create([
        'user_id' => $bob->id,
        'name' => 'Other',
        'rigor_level' => 1,
    ]);
    $bobMilestone = $bobProject->milestones()->create([
        'name' => 'Bob',
        'status' => 'pending',
    ]);

    $this->actingAs($this->user);

    Livewire::test('pages::milestones.edit-modal')
        ->call('load', $bobMilestone->id)
        ->assertStatus(404);
});

test('owner can delete a milestone', function () {
    $milestone = $this->project->milestones()->create([
        'name' => 'CDR',
        'status' => 'pending',
    ]);

    $this->actingAs($this->user);

    Livewire::test('pages::milestones.delete-modal')
        ->call('load', $milestone->id)
        ->call('delete');

    expect(Milestone::find($milestone->id))->toBeNull();
});

test('milestone delete 404s for another owner', function () {
    $bob = User::factory()->create();
    $bobProject = Project::create([
        'user_id' => $bob->id,
        'name' => 'Other',
        'rigor_level' => 1,
    ]);
    $bobMilestone = $bobProject->milestones()->create([
        'name' => 'Bob',
        'status' => 'pending',
    ]);

    $this->actingAs($this->user);

    Livewire::test('pages::milestones.delete-modal')
        ->call('load', $bobMilestone->id)
        ->assertStatus(404);

    expect(Milestone::withoutGlobalScopes()->find($bobMilestone->id))->not->toBeNull();
});

test('plan page renders New milestone button', function () {
    $this->actingAs($this->user)
        ->get('/plan?project='.$this->project->id)
        ->assertOk()
        ->assertSee('New milestone');
});
