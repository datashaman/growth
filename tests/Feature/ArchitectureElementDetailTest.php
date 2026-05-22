<?php

/*
 * #376: design elements are clickable from the Architecture surface and have
 * their own detail page showing the parent view plus the concerns and
 * citations carried by that view.
 */

use App\Models\Citation;
use App\Models\Concern;
use App\Models\Project;
use App\Models\Source;
use App\Models\Stakeholder;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->project = Project::create([
        'workspace_id' => $this->user->active_workspace_id,
        'name' => 'Lunar Lander',
        'rigor_level' => 2,
    ]);

    $this->view = $this->project->designViews()->create([
        'viewpoint' => 'logical',
        'name' => 'Descent stack',
        'description' => 'Modules involved in landing.',
    ]);

    $this->element = $this->view->elements()->create([
        'kind' => 'entity',
        'name' => 'GuidanceComputer',
        'type' => 'component',
        'purpose' => 'Computes the powered-descent trajectory.',
    ]);
});

test('architecture page links each element to its detail page', function () {
    $this->actingAs($this->user)
        ->get('/architecture?project='.$this->project->id)
        ->assertOk()
        ->assertSee('GuidanceComputer')
        ->assertSee(route('architecture-elements.show', $this->element), false);
});

test('the element detail page renders the element, its kind, purpose, and parent view', function () {
    $this->actingAs($this->user)
        ->get(route('architecture-elements.show', $this->element))
        ->assertOk()
        ->assertSee('GuidanceComputer')
        ->assertSee('entity')
        ->assertSee('Computes the powered-descent trajectory.')
        ->assertSee('Descent stack')
        ->assertSee(route('architecture', ['project' => $this->project->id]), false)
        ->assertSee('GuidanceComputer - '.config('app.name'), false);
});

test('the element detail page shows the concerns framed by its view', function () {
    $stakeholder = Stakeholder::create([
        'project_id' => $this->project->id,
        'name' => 'Mission Director',
        'role' => 'sponsor',
        'kind' => 'individual',
    ]);
    $concern = Concern::create([
        'project_id' => $this->project->id,
        'raised_by_stakeholder_id' => $stakeholder->id,
        'text' => 'Re-entry heating margins',
    ]);
    $this->view->concerns()->attach($concern);

    $this->actingAs($this->user)
        ->get(route('architecture-elements.show', $this->element))
        ->assertOk()
        ->assertSee('Concerns framed by this view')
        ->assertSee('Re-entry heating margins')
        ->assertSee('Mission Director');
});

test('the element detail page shows the citations on its view', function () {
    $source = Source::create([
        'project_id' => $this->project->id,
        'kind' => 'doc',
        'title' => 'Descent Control Spec',
    ]);
    Citation::create([
        'source_id' => $source->id,
        'citable_type' => $this->view->getMorphClass(),
        'citable_id' => $this->view->id,
        'quote' => 'The guidance computer owns the descent loop.',
        'locator' => 'section 4.2',
    ]);

    $this->actingAs($this->user)
        ->get(route('architecture-elements.show', $this->element))
        ->assertOk()
        ->assertSee('Citations on this view')
        ->assertSee('Descent Control Spec')
        ->assertSee('section 4.2');
});
