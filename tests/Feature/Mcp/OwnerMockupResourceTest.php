<?php

use App\Mcp\Servers\ReadonlyServer;
use App\Models\Concern;
use App\Models\DesignElement;
use App\Models\DesignView;
use App\Models\Project;
use App\Models\Requirement;
use App\Models\User;
use App\Models\WorkItem;
use Laravel\Passport\Passport;

beforeEach(function () {
    $this->user = User::factory()->create();
    Passport::actingAs($this->user, ['mcp:use']);

    $this->project = Project::create([
        'workspace_id' => $this->user->active_workspace_id,
        'name' => 'Mockups',
        'rigor_level' => 2,
    ]);

    $this->workItem = WorkItem::create([
        'project_id' => $this->project->id,
        'kind' => WorkItem::KINDS[0],
        'name' => 'Ship it',
    ]);
});

it("serves a work item's default mockup current revision html", function () {
    $mockup = createMockup($this->workItem, 'default', '<!doctype html><html><body>v1</body></html>');
    $mockup->appendRevision('<!doctype html><html><body>v2</body></html>');

    readResource(ReadonlyServer::class, "growth://owners/work_item/{$this->workItem->id}/mockup")
        ->assertOk()
        ->assertSee('v2')
        ->assertDontSee('v1');
});

it("serves a requirement's default mockup", function () {
    $requirement = Requirement::create([
        'project_id' => $this->project->id,
        'doc' => 'srs',
        'type' => 'functional',
        'text' => 'The checkout must be one page',
    ]);
    createMockup($requirement, 'default', '<!doctype html><html><body>one-page</body></html>');

    readResource(ReadonlyServer::class, "growth://owners/requirement/{$requirement->id}/mockup")
        ->assertOk()
        ->assertSee('one-page');
});

it('ignores named alternatives, only returning the default', function () {
    createMockup($this->workItem, 'Compact layout', '<!doctype html><html><body>compact</body></html>');

    readResource(ReadonlyServer::class, "growth://owners/work_item/{$this->workItem->id}/mockup")
        ->assertHasErrors(['No default mockup']);
});

it('errors when the owner has no default mockup', function () {
    readResource(ReadonlyServer::class, "growth://owners/work_item/{$this->workItem->id}/mockup")
        ->assertHasErrors(['No default mockup']);
});

it('does not serve a default mockup from another workspace', function () {
    $other = User::factory()->create();
    $otherProject = Project::create([
        'workspace_id' => $other->active_workspace_id,
        'name' => 'Theirs',
        'rigor_level' => 2,
    ]);
    $otherItem = WorkItem::create([
        'project_id' => $otherProject->id,
        'kind' => WorkItem::KINDS[0],
        'name' => 'Theirs',
    ]);
    createMockup($otherItem, 'default', '<!doctype html><html><body>secret</body></html>');

    readResource(ReadonlyServer::class, "growth://owners/work_item/{$otherItem->id}/mockup")
        ->assertHasErrors(['No default mockup']);
});

it('serves a mockup design brief for a work item', function () {
    $requirement = Requirement::create([
        'project_id' => $this->project->id,
        'doc' => 'srs',
        'type' => 'functional',
        'text' => 'The checkout must show payment status and reject invalid cards.',
        'acceptance_criteria' => ['Payment status is visible before submit.', 'Invalid cards show a validation error.'],
        'renders_ui' => true,
    ]);
    $this->workItem->forceFill(['needs_mockups' => true])->save();
    $this->workItem->requirements()->attach($requirement->id);

    $concern = Concern::create([
        'project_id' => $this->project->id,
        'text' => 'Users need clear payment feedback.',
    ]);
    $view = DesignView::create([
        'project_id' => $this->project->id,
        'viewpoint' => 'interaction',
        'name' => 'Checkout interaction',
        'description' => 'Payment state flow.',
    ]);
    $view->concerns()->attach($concern->id);
    DesignElement::create([
        'design_view_id' => $view->id,
        'kind' => 'entity',
        'name' => 'Payment status panel',
        'type' => 'ui_component',
        'purpose' => 'Shows whether payment is pending, accepted, or failed.',
    ]);
    $mockup = createMockup($this->workItem, 'default', '<!doctype html><html><body>v1</body></html>');

    readResource(ReadonlyServer::class, "growth://owners/work_item/{$this->workItem->id}/mockup-design-brief")
        ->assertOk()
        ->assertSee('Mockup Design Brief - Mockups')
        ->assertSee($this->workItem->reference())
        ->assertSee('The checkout must show payment status and reject invalid cards.')
        ->assertSee('Payment status is visible before submit.')
        ->assertSee('Expected Screen Coverage')
        ->assertSee('Cover UI requirement')
        ->assertSee('validation failure')
        ->assertSee('separate named mockups')
        ->assertSee('filtering, toggles, inline validation')
        ->assertSee('Checkout interaction')
        ->assertSee('Users need clear payment feedback.')
        ->assertSee('Payment status panel')
        ->assertSee("growth://mockups/{$mockup->id}")
        ->assertSee('Represent relevant architecture views/elements');
});
