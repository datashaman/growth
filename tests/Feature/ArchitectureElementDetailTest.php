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

test('the relationship detail page shows resolved source and target elements', function () {
    $flightSoftware = $this->view->elements()->create([
        'kind' => 'entity',
        'name' => 'FlightSoftware',
        'type' => 'component',
        'purpose' => 'Executes guidance commands.',
    ]);
    $relationship = $this->view->elements()->create([
        'kind' => 'relationship',
        'name' => 'Guidance commands',
        'type' => 'dependency',
        'purpose' => 'GuidanceComputer sends commands to FlightSoftware.',
        'properties' => [
            'source_id' => $this->element->id,
            'target_id' => $flightSoftware->id,
            'protocol' => 'command bus',
        ],
    ]);

    $this->actingAs($this->user)
        ->get(route('architecture-elements.show', $relationship))
        ->assertOk()
        ->assertSee('Relationship endpoints')
        ->assertSee('Source')
        ->assertSee('Target')
        ->assertSee('GuidanceComputer')
        ->assertSee('FlightSoftware')
        ->assertSee(route('architecture-elements.show', $this->element), false)
        ->assertSee(route('architecture-elements.show', $flightSoftware), false)
        ->assertSee('Properties')
        ->assertSee('Protocol')
        ->assertSee('command bus')
        ->assertDontSee('Source Id')
        ->assertDontSee('Target Id');
});

test('the element detail page shows structured properties for non relationship elements', function () {
    $attribute = $this->view->elements()->create([
        'kind' => 'attribute',
        'name' => 'Telemetry freshness',
        'type' => 'quality',
        'purpose' => 'Defines the expected telemetry latency.',
        'properties' => [
            'owner' => 'flight operations',
            'max_latency_ms' => 500,
            'required' => true,
        ],
    ]);

    $this->actingAs($this->user)
        ->get(route('architecture-elements.show', $attribute))
        ->assertOk()
        ->assertSee('Properties')
        ->assertSee('Owner')
        ->assertSee('flight operations')
        ->assertSee('Max Latency Ms')
        ->assertSee('500')
        ->assertSee('Required')
        ->assertSee('true');
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
