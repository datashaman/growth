<?php

use App\Models\CustomViewpoint;
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

test('owner can create a custom viewpoint', function () {
    $this->actingAs($this->user);

    Livewire::test('pages::custom-viewpoints.create-modal', ['projectId' => $this->project->id])
        ->set('name', 'safety')
        ->set('concerns', 'safety, fault tolerance')
        ->set('element_types', 'hazard, mitigation')
        ->set('languages', 'STPA')
        ->set('source', 'IEC 61508')
        ->call('save')
        ->assertHasNoErrors();

    $viewpoint = CustomViewpoint::query()->where('name', 'safety')->first();
    expect($viewpoint)->not->toBeNull()
        ->and($viewpoint->project_id)->toBe($this->project->id)
        ->and($viewpoint->concerns)->toBe(['safety', 'fault tolerance'])
        ->and($viewpoint->element_types)->toBe(['hazard', 'mitigation'])
        ->and($viewpoint->languages)->toBe(['STPA'])
        ->and($viewpoint->source)->toBe('IEC 61508');
});

test('create rejects built-in viewpoint names', function () {
    $this->actingAs($this->user);

    Livewire::test('pages::custom-viewpoints.create-modal', ['projectId' => $this->project->id])
        ->set('name', 'logical')
        ->set('concerns', 'x')
        ->set('element_types', 'y')
        ->set('languages', 'z')
        ->call('save')
        ->assertHasErrors('name');
});

test('viewpoint name is unique per project', function () {
    $this->project->customViewpoints()->create([
        'name' => 'safety',
        'concerns' => ['x'], 'element_types' => ['y'], 'languages' => ['z'],
    ]);
    $this->actingAs($this->user);

    Livewire::test('pages::custom-viewpoints.create-modal', ['projectId' => $this->project->id])
        ->set('name', 'safety')
        ->set('concerns', 'x')
        ->set('element_types', 'y')
        ->set('languages', 'z')
        ->call('save')
        ->assertHasErrors('name');
});

test('create requires non-empty arrays after csv parse', function () {
    $this->actingAs($this->user);

    Livewire::test('pages::custom-viewpoints.create-modal', ['projectId' => $this->project->id])
        ->set('name', 'foo')
        ->set('concerns', ' , ,')
        ->set('element_types', 'a')
        ->set('languages', 'b')
        ->call('save')
        ->assertHasErrors('concerns');
});

test('owner can edit a custom viewpoint', function () {
    $viewpoint = $this->project->customViewpoints()->create([
        'name' => 'safety',
        'concerns' => ['a'], 'element_types' => ['b'], 'languages' => ['c'],
    ]);
    $this->actingAs($this->user);

    Livewire::test('pages::custom-viewpoints.edit-modal')
        ->call('load', $viewpoint->id)
        ->set('concerns', 'a, b, c')
        ->call('save')
        ->assertHasNoErrors();

    expect($viewpoint->fresh()->concerns)->toBe(['a', 'b', 'c']);
});

test('edit 404s for another owner', function () {
    $bob = User::factory()->create();
    $bobProject = Project::create([
        'user_id' => $bob->id, 'name' => 'Bob', 'rigor_level' => 1,
    ]);
    $bobViewpoint = $bobProject->customViewpoints()->create([
        'name' => 'safety',
        'concerns' => ['a'], 'element_types' => ['b'], 'languages' => ['c'],
    ]);
    $this->actingAs($this->user);

    Livewire::test('pages::custom-viewpoints.edit-modal')
        ->call('load', $bobViewpoint->id)
        ->assertStatus(404);
});

test('owner can delete a custom viewpoint', function () {
    $viewpoint = $this->project->customViewpoints()->create([
        'name' => 'safety',
        'concerns' => ['a'], 'element_types' => ['b'], 'languages' => ['c'],
    ]);
    $this->actingAs($this->user);

    Livewire::test('pages::custom-viewpoints.delete-modal')
        ->call('load', $viewpoint->id)
        ->call('delete');

    expect(CustomViewpoint::find($viewpoint->id))->toBeNull();
});

test('delete surfaces design-view usage count', function () {
    $viewpoint = $this->project->customViewpoints()->create([
        'name' => 'safety',
        'concerns' => ['a'], 'element_types' => ['b'], 'languages' => ['c'],
    ]);
    $this->project->designViews()->create([
        'viewpoint' => 'safety', 'name' => 'Hazard view',
    ]);
    $this->actingAs($this->user);

    Livewire::test('pages::custom-viewpoints.delete-modal')
        ->call('load', $viewpoint->id)
        ->assertSet('usageCount', 1);
});

test('architecture page exposes a New viewpoint button', function () {
    $this->actingAs($this->user)
        ->get('/architecture?project='.$this->project->id)
        ->assertOk()
        ->assertSee('New viewpoint');
});
