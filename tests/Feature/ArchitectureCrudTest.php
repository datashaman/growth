<?php

use App\Models\DesignElement;
use App\Models\DesignView;
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

test('owner can create a design view with a built-in viewpoint', function () {
    $this->actingAs($this->user);

    Livewire::test('pages::design-views.create-modal', ['projectId' => $this->project->id])
        ->set('name', 'Logical structure')
        ->set('viewpoint', 'logical')
        ->set('description', 'Top-level decomposition.')
        ->call('save')
        ->assertHasNoErrors();

    $view = DesignView::query()->where('name', 'Logical structure')->first();
    expect($view)->not->toBeNull()
        ->and($view->project_id)->toBe($this->project->id);
});

test('design view rejects unknown viewpoint', function () {
    $this->actingAs($this->user);

    Livewire::test('pages::design-views.create-modal', ['projectId' => $this->project->id])
        ->set('name', 'x')
        ->set('viewpoint', 'made-up-viewpoint')
        ->call('save')
        ->assertHasErrors('viewpoint');
});

test('design view accepts a custom viewpoint for this project', function () {
    $this->project->customViewpoints()->create([
        'name' => 'Quantum',
        'concerns' => ['superposition'],
        'element_types' => ['qubit'],
        'languages' => ['qsharp'],
    ]);
    $this->actingAs($this->user);

    Livewire::test('pages::design-views.create-modal', ['projectId' => $this->project->id])
        ->set('name', 'Quantum view')
        ->set('viewpoint', 'Quantum')
        ->call('save')
        ->assertHasNoErrors();
});

test('owner can edit a design view', function () {
    $view = $this->project->designViews()->create([
        'name' => 'Old', 'viewpoint' => 'logical',
    ]);
    $this->actingAs($this->user);

    Livewire::test('pages::design-views.edit-modal')
        ->call('load', $view->id)
        ->set('name', 'New')
        ->call('save')
        ->assertHasNoErrors();

    expect($view->fresh()->name)->toBe('New');
});

test('design view edit 404s for another owner', function () {
    $bob = User::factory()->create();
    $bobProject = Project::create([
        'user_id' => $bob->id, 'name' => 'Other', 'rigor_level' => 1,
    ]);
    $bobView = $bobProject->designViews()->create([
        'name' => 'Bob', 'viewpoint' => 'logical',
    ]);
    $this->actingAs($this->user);

    Livewire::test('pages::design-views.edit-modal')
        ->call('load', $bobView->id)
        ->assertStatus(404);
});

test('owner can delete a design view', function () {
    $view = $this->project->designViews()->create([
        'name' => 'Doomed', 'viewpoint' => 'logical',
    ]);
    $this->actingAs($this->user);

    Livewire::test('pages::design-views.delete-modal')
        ->call('load', $view->id)
        ->call('delete');

    expect(DesignView::find($view->id))->toBeNull();
});

test('owner can add an element to a view', function () {
    $view = $this->project->designViews()->create([
        'name' => 'V', 'viewpoint' => 'logical',
    ]);
    $this->actingAs($this->user);

    Livewire::test('pages::design-elements.create-modal')
        ->call('load', $view->id)
        ->set('name', 'AuthService')
        ->set('kind', 'entity')
        ->set('type', 'service')
        ->call('save')
        ->assertHasNoErrors();

    $element = DesignElement::query()->where('name', 'AuthService')->first();
    expect($element)->not->toBeNull()
        ->and($element->design_view_id)->toBe($view->id);
});

test('element kind must be valid', function () {
    $view = $this->project->designViews()->create([
        'name' => 'V', 'viewpoint' => 'logical',
    ]);
    $this->actingAs($this->user);

    Livewire::test('pages::design-elements.create-modal')
        ->call('load', $view->id)
        ->set('name', 'X')
        ->set('kind', 'monster')
        ->call('save')
        ->assertHasErrors('kind');
});

test('element create 404s for a view in another project', function () {
    $bob = User::factory()->create();
    $bobProject = Project::create([
        'user_id' => $bob->id, 'name' => 'Other', 'rigor_level' => 1,
    ]);
    $bobView = $bobProject->designViews()->create([
        'name' => 'Bob', 'viewpoint' => 'logical',
    ]);
    $this->actingAs($this->user);

    Livewire::test('pages::design-elements.create-modal')
        ->call('load', $bobView->id)
        ->assertStatus(404);
});

test('owner can edit an element', function () {
    $view = $this->project->designViews()->create([
        'name' => 'V', 'viewpoint' => 'logical',
    ]);
    $element = $view->elements()->create([
        'kind' => 'entity', 'name' => 'Old',
    ]);
    $this->actingAs($this->user);

    Livewire::test('pages::design-elements.edit-modal')
        ->call('load', $element->id)
        ->set('name', 'New')
        ->call('save')
        ->assertHasNoErrors();

    expect($element->fresh()->name)->toBe('New');
});

test('owner can delete an element', function () {
    $view = $this->project->designViews()->create([
        'name' => 'V', 'viewpoint' => 'logical',
    ]);
    $element = $view->elements()->create([
        'kind' => 'entity', 'name' => 'Doomed',
    ]);
    $this->actingAs($this->user);

    Livewire::test('pages::design-elements.delete-modal')
        ->call('load', $element->id)
        ->call('delete');

    expect(DesignElement::find($element->id))->toBeNull();
});

test('architecture page renders New view button', function () {
    $this->actingAs($this->user)
        ->get('/architecture?project='.$this->project->id)
        ->assertOk()
        ->assertSee('New view');
});
