<?php

use App\Mcp\Servers\PlanningServer;
use App\Mcp\Tools\Plan\GetMockup;
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

it("returns the default mockup's current html for a work item", function () {
    $mockup = createMockup($this->workItem, 'default', '<!doctype html><html><body>v1</body></html>');
    $mockup->appendRevision('<!doctype html><html><body>v2</body></html>');

    PlanningServer::tool(GetMockup::class, [
        'owner_type' => 'work_item',
        'owner_id' => $this->workItem->id,
    ])
        ->assertOk()
        ->assertStructuredContent(function ($json) use ($mockup) {
            $json->where('id', $mockup->id)
                ->where('name', 'default')
                ->where('revision', 2)
                ->where('html', '<!doctype html><html><body>v2</body></html>')
                ->etc();
        });
});

it('returns a named alternative when name is supplied', function () {
    createMockup($this->workItem, 'default', '<!doctype html><html><body>default</body></html>');
    $compact = createMockup($this->workItem, 'Compact layout', '<!doctype html><html><body>compact</body></html>');

    PlanningServer::tool(GetMockup::class, [
        'owner_type' => 'work_item',
        'owner_id' => $this->workItem->id,
        'name' => 'Compact layout',
    ])
        ->assertOk()
        ->assertStructuredContent(function ($json) use ($compact) {
            $json->where('id', $compact->id)
                ->where('name', 'Compact layout')
                ->where('html', '<!doctype html><html><body>compact</body></html>')
                ->etc();
        });
});

it('returns an error when no mockup matches the requested name', function () {
    createMockup($this->workItem, 'default', '<!doctype html><html><body>default</body></html>');

    PlanningServer::tool(GetMockup::class, [
        'owner_type' => 'work_item',
        'owner_id' => $this->workItem->id,
        'name' => 'Nonexistent',
    ])->assertHasErrors(['No mockup named [Nonexistent]']);
});

it('returns an error when the work item has no default mockup', function () {
    PlanningServer::tool(GetMockup::class, [
        'owner_type' => 'work_item',
        'owner_id' => $this->workItem->id,
    ])->assertHasErrors(['No mockup named [default]']);
});

it("returns a requirement's default mockup", function () {
    $requirement = Requirement::create([
        'project_id' => $this->project->id,
        'doc' => 'srs',
        'type' => 'functional',
        'text' => 'The checkout must be one page',
    ]);
    createMockup($requirement, 'default', '<!doctype html><html><body>one</body></html>');

    PlanningServer::tool(GetMockup::class, [
        'owner_type' => 'requirement',
        'owner_id' => $requirement->id,
    ])
        ->assertOk()
        ->assertStructuredContent(function ($json) use ($requirement) {
            $json->where('owner_type', 'requirement')
                ->where('owner_id', $requirement->id)
                ->where('html', '<!doctype html><html><body>one</body></html>')
                ->etc();
        });
});

it('rejects an owner from another workspace', function () {
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

    PlanningServer::tool(GetMockup::class, [
        'owner_type' => 'work_item',
        'owner_id' => $otherItem->id,
    ])->assertHasErrors();
});
