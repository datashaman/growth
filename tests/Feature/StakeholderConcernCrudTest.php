<?php

use App\Models\Concern;
use App\Models\Project;
use App\Models\Stakeholder;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->project = Project::create([
        'user_id' => $this->user->id,
        'name' => 'Lunar Lander',
        'integrity_level' => 2,
    ]);
});

test('owner can create a stakeholder', function () {
    $this->actingAs($this->user);

    Livewire::test('pages::stakeholders.create-modal', ['projectId' => $this->project->id])
        ->set('name', 'NASA PM')
        ->set('role', 'Sponsor')
        ->set('kind', 'individual')
        ->set('description', 'Authority on mission objectives.')
        ->call('save')
        ->assertHasNoErrors();

    $stakeholder = Stakeholder::query()->where('name', 'NASA PM')->first();
    expect($stakeholder)->not->toBeNull()
        ->and($stakeholder->project_id)->toBe($this->project->id)
        ->and($stakeholder->kind)->toBe('individual');
});

test('stakeholder create requires name', function () {
    $this->actingAs($this->user);

    Livewire::test('pages::stakeholders.create-modal', ['projectId' => $this->project->id])
        ->set('name', '')
        ->call('save')
        ->assertHasErrors(['name' => 'required']);
});

test('stakeholder kind must be from the allowed list', function () {
    $this->actingAs($this->user);

    Livewire::test('pages::stakeholders.create-modal', ['projectId' => $this->project->id])
        ->set('name', 'x')
        ->set('kind', 'committee')
        ->call('save')
        ->assertHasErrors(['kind']);
});

test('owner can edit a stakeholder', function () {
    $stakeholder = $this->project->stakeholders()->create(['name' => 'Old']);
    $this->actingAs($this->user);

    Livewire::test('pages::stakeholders.edit-modal')
        ->call('load', $stakeholder->id)
        ->set('name', 'New')
        ->call('save')
        ->assertHasNoErrors();

    expect($stakeholder->fresh()->name)->toBe('New');
});

test('stakeholder edit 404s for another owner', function () {
    $bob = User::factory()->create();
    $bobProject = Project::create([
        'user_id' => $bob->id, 'name' => 'Other', 'integrity_level' => 1,
    ]);
    $bobStakeholder = $bobProject->stakeholders()->create(['name' => 'Spy']);
    $this->actingAs($this->user);

    Livewire::test('pages::stakeholders.edit-modal')
        ->call('load', $bobStakeholder->id)
        ->assertStatus(404);
});

test('owner can delete a stakeholder', function () {
    $stakeholder = $this->project->stakeholders()->create(['name' => 'Doomed']);
    $this->actingAs($this->user);

    Livewire::test('pages::stakeholders.delete-modal')
        ->call('load', $stakeholder->id)
        ->call('delete');

    expect(Stakeholder::find($stakeholder->id))->toBeNull();
});

test('stakeholder delete shows concern count warning', function () {
    $stakeholder = $this->project->stakeholders()->create(['name' => 'Vocal']);
    $this->project->concerns()->create([
        'text' => 'Concern A',
        'raised_by_stakeholder_id' => $stakeholder->id,
    ]);
    $this->actingAs($this->user);

    Livewire::test('pages::stakeholders.delete-modal')
        ->call('load', $stakeholder->id)
        ->assertSet('concernCount', 1);
});

test('owner can create a concern', function () {
    $stakeholder = $this->project->stakeholders()->create(['name' => 'Sponsor']);
    $this->actingAs($this->user);

    Livewire::test('pages::concerns.create-modal', ['projectId' => $this->project->id])
        ->set('text', 'System must survive re-entry.')
        ->set('raised_by_stakeholder_id', $stakeholder->id)
        ->set('viewpoint_hints_text', 'thermal, structural')
        ->call('save')
        ->assertHasNoErrors();

    $concern = Concern::query()->first();
    expect($concern)->not->toBeNull()
        ->and($concern->project_id)->toBe($this->project->id)
        ->and($concern->raised_by_stakeholder_id)->toBe($stakeholder->id)
        ->and($concern->viewpoint_hints)->toBe(['thermal', 'structural']);
});

test('concern rejects foreign stakeholder', function () {
    $bob = User::factory()->create();
    $bobProject = Project::create([
        'user_id' => $bob->id, 'name' => 'Other', 'integrity_level' => 1,
    ]);
    $foreignStakeholder = $bobProject->stakeholders()->create(['name' => 'Spy']);
    $this->actingAs($this->user);

    Livewire::test('pages::concerns.create-modal', ['projectId' => $this->project->id])
        ->set('text', 'x')
        ->set('raised_by_stakeholder_id', $foreignStakeholder->id)
        ->call('save')
        ->assertHasErrors(['raised_by_stakeholder_id']);
});

test('owner can edit a concern', function () {
    $concern = $this->project->concerns()->create(['text' => 'Original']);
    $this->actingAs($this->user);

    Livewire::test('pages::concerns.edit-modal')
        ->call('load', $concern->id)
        ->set('text', 'Updated')
        ->call('save')
        ->assertHasNoErrors();

    expect($concern->fresh()->text)->toBe('Updated');
});

test('concern edit 404s for another owner', function () {
    $bob = User::factory()->create();
    $bobProject = Project::create([
        'user_id' => $bob->id, 'name' => 'Other', 'integrity_level' => 1,
    ]);
    $bobConcern = $bobProject->concerns()->create(['text' => 'Bob']);
    $this->actingAs($this->user);

    Livewire::test('pages::concerns.edit-modal')
        ->call('load', $bobConcern->id)
        ->assertStatus(404);
});

test('owner can delete a concern', function () {
    $concern = $this->project->concerns()->create(['text' => 'Doomed']);
    $this->actingAs($this->user);

    Livewire::test('pages::concerns.delete-modal')
        ->call('load', $concern->id)
        ->call('delete');

    expect(Concern::find($concern->id))->toBeNull();
});

test('intent page renders both New buttons', function () {
    $this->actingAs($this->user)
        ->get('/intent?project='.$this->project->id)
        ->assertOk()
        ->assertSee('New stakeholder')
        ->assertSee('New concern');
});
