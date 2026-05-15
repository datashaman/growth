<?php

use App\Models\Project;
use App\Models\Requirement;
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

test('owner can create a requirement from the create page', function () {
    $this->actingAs($this->user);

    $this->get('/requirements/create?project='.$this->project->id)->assertOk();

    Livewire::test('pages::requirements.create', ['project' => $this->project->id])
        ->set('doc', 'srs')
        ->set('type', 'functional')
        ->set('text', 'System shall ignite descent engine at T-10s.')
        ->set('priority', 'high')
        ->set('source', 'Stakeholder review')
        ->set('acceptance_criteria_text', "Engine ignites\nThrust nominal")
        ->set('tags_text', 'descent, propulsion')
        ->call('save')
        ->assertHasNoErrors();

    $requirement = Requirement::query()->where('text', 'like', '%descent engine%')->first();
    expect($requirement)->not->toBeNull()
        ->and($requirement->project_id)->toBe($this->project->id)
        ->and($requirement->acceptance_criteria)->toBe(['Engine ignites', 'Thrust nominal'])
        ->and($requirement->tags)->toBe(['descent', 'propulsion']);
});

test('create page 404s when project does not belong to user', function () {
    $bob = User::factory()->create();
    $bobProject = Project::create([
        'workspace_id' => $bob->active_workspace_id,
        'name' => 'Hostile',
        'rigor_level' => 1,
    ]);

    $this->actingAs($this->user)
        ->get('/requirements/create?project='.$bobProject->id)
        ->assertNotFound();
});

test('create requires text', function () {
    $this->actingAs($this->user);

    $this->get('/requirements/create?project='.$this->project->id);

    Livewire::test('pages::requirements.create', ['project' => $this->project->id])
        ->set('text', '')
        ->call('save')
        ->assertHasErrors(['text' => 'required']);
});

test('create rejects parent_id from another project', function () {
    $otherUser = User::factory()->create();
    $otherProject = Project::create([
        'workspace_id' => $otherUser->active_workspace_id,
        'name' => 'Other',
        'rigor_level' => 1,
    ]);
    $foreignParent = $otherProject->requirements()->create([
        'doc' => 'srs', 'type' => 'functional', 'text' => 'Foreign',
    ]);

    $this->actingAs($this->user);
    $this->get('/requirements/create?project='.$this->project->id);

    Livewire::test('pages::requirements.create', ['project' => $this->project->id])
        ->set('text', 'X')
        ->set('parent_id', $foreignParent->id)
        ->call('save')
        ->assertHasErrors(['parent_id']);
});

test('owner can edit a requirement', function () {
    $requirement = $this->project->requirements()->create([
        'doc' => 'srs', 'type' => 'functional', 'text' => 'Original.',
    ]);

    $this->actingAs($this->user);
    $this->get('/requirements/'.$requirement->id.'/edit')->assertOk()->assertSee('Original.');

    Livewire::test('pages::requirements.edit', ['requirement' => $requirement])
        ->set('text', 'Updated statement.')
        ->set('priority', 'medium')
        ->call('save')
        ->assertHasNoErrors();

    $requirement->refresh();
    expect($requirement->text)->toBe('Updated statement.')
        ->and($requirement->priority)->toBe('medium');
});

test('edit page 404s for a requirement in another project', function () {
    $bob = User::factory()->create();
    $bobProject = Project::create([
        'workspace_id' => $bob->active_workspace_id,
        'name' => 'Other',
        'rigor_level' => 1,
    ]);
    $bobReq = $bobProject->requirements()->create([
        'doc' => 'srs', 'type' => 'functional', 'text' => 'Bob.',
    ]);

    $this->actingAs($this->user)
        ->get('/requirements/'.$bobReq->id.'/edit')
        ->assertNotFound();
});

test('owner can delete a requirement', function () {
    $requirement = $this->project->requirements()->create([
        'doc' => 'srs', 'type' => 'functional', 'text' => 'Delete me.',
    ]);

    $this->actingAs($this->user);

    Livewire::test('pages::requirements.delete-modal', ['requirementId' => $requirement->id])
        ->call('delete');

    expect(Requirement::find($requirement->id))->toBeNull();
});

test('delete modal 404s for a requirement in another project', function () {
    $bob = User::factory()->create();
    $bobProject = Project::create([
        'workspace_id' => $bob->active_workspace_id,
        'name' => 'Other',
        'rigor_level' => 1,
    ]);
    $bobReq = $bobProject->requirements()->create([
        'doc' => 'srs', 'type' => 'functional', 'text' => 'Bob.',
    ]);

    $this->actingAs($this->user);

    Livewire::test('pages::requirements.delete-modal', ['requirementId' => $bobReq->id])
        ->call('delete')
        ->assertStatus(404);

    expect(Requirement::withoutGlobalScopes()->find($bobReq->id))->not->toBeNull();
});

test('requirements page renders New requirement button', function () {
    $this->actingAs($this->user)
        ->get('/requirements?project='.$this->project->id)
        ->assertOk()
        ->assertSee('New requirement');
});
