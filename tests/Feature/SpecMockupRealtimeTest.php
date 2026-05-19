<?php

use App\Models\Project;
use App\Models\User;
use App\Models\WorkItem;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    $this->project = Project::create([
        'workspace_id' => $this->user->active_workspace_id,
        'name' => 'Mockups',
        'rigor_level' => 2,
    ]);
    $this->workItem = WorkItem::create([
        'project_id' => $this->project->id,
        'kind' => WorkItem::KINDS[0],
        'name' => 'Checkout',
    ]);
    $this->mockup = createMockup(
        $this->workItem,
        'Checkout layout',
        '<!doctype html><html><body><h1>v1</h1></body></html>',
    );
});

it('subscribes the mockup page to the workspace data-changed broadcast', function () {
    $listeners = Livewire::test('pages::mockups.show', ['mockup' => $this->mockup])
        ->instance()
        ->getListeners();

    expect($listeners)->toHaveKey(
        'echo-private:workspaces.'.$this->user->active_workspace_id.',WorkspaceDataChanged'
    );
});

it('reflects a new revision without a manual reload', function () {
    $component = Livewire::test('pages::mockups.show', ['mockup' => $this->mockup]);

    // Only one revision so far — the revision nav is not shown yet.
    $component->assertDontSee(__('Revision :number', ['number' => 2]));

    // A new revision arrives — e.g. via the upsert-mockup MCP tool.
    $this->mockup->appendRevision('<!doctype html><html><body><h1>v2</h1></body></html>');

    $component->call('onWorkspaceDataChanged')
        ->assertSee(__('Revision :number', ['number' => 2]));
});

it('follows a new revision for a viewer on the current one', function () {
    $component = Livewire::test('pages::mockups.show', ['mockup' => $this->mockup]);

    $revisionTwo = $this->mockup->appendRevision('<!doctype html><html><body><h1>v2</h1></body></html>');

    $component->call('onWorkspaceDataChanged')
        ->assertSet('revisionId', $revisionTwo->id);
});

it('leaves a deliberately selected past revision in place on refresh', function () {
    $revisionOne = $this->mockup->revisions->first();

    $component = Livewire::test('pages::mockups.show', ['mockup' => $this->mockup]);
    $this->mockup->appendRevision('<!doctype html><html><body><h1>v2</h1></body></html>');

    // The viewer steps back to revision 1, then a third revision arrives.
    $component->call('selectRevision', $revisionOne->id)
        ->call('onWorkspaceDataChanged')
        ->assertSet('revisionId', $revisionOne->id);
});
