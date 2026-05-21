<?php

use App\Mcp\Servers\ReadonlyServer;
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
