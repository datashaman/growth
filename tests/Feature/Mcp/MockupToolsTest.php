<?php

use App\Mcp\Servers\PlanningServer;
use App\Mcp\Tools\Plan\DeleteMockup;
use App\Mcp\Tools\Plan\ListMockups;
use App\Models\Project;
use App\Models\SpecMockup;
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

it('lists a work item mockups without their html', function () {
    SpecMockup::create([
        'work_item_id' => $this->workItem->id,
        'name' => 'Roomy layout',
        'html' => '<!doctype html><html><body>roomy</body></html>',
    ]);
    SpecMockup::create([
        'work_item_id' => $this->workItem->id,
        'name' => 'Compact layout',
        'html' => '<!doctype html><html><body>compact</body></html>',
    ]);

    PlanningServer::tool(ListMockups::class, ['work_item_id' => $this->workItem->id])
        ->assertOk()
        ->assertStructuredContent(function ($json) {
            $json->where('total', 2)
                ->where('results.0.name', 'Compact layout')
                ->where('results.1.name', 'Roomy layout')
                ->etc();
        });
});

it('does not list mockups for a work item in another workspace', function () {
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

    PlanningServer::tool(ListMockups::class, ['work_item_id' => $otherItem->id])
        ->assertHasErrors();
});

it('deletes a mockup', function () {
    $mockup = SpecMockup::create([
        'work_item_id' => $this->workItem->id,
        'name' => 'Roomy layout',
        'html' => '<!doctype html><html><body>roomy</body></html>',
    ]);

    PlanningServer::tool(DeleteMockup::class, ['id' => $mockup->id])
        ->assertOk()
        ->assertStructuredContent(function ($json) use ($mockup) {
            $json->where('id', $mockup->id)
                ->where('deleted', true)
                ->etc();
        });

    expect(SpecMockup::find($mockup->id))->toBeNull();
});

it('does not delete a mockup from another workspace', function () {
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
    $otherMockup = SpecMockup::create([
        'work_item_id' => $otherItem->id,
        'name' => 'Their layout',
        'html' => '<!doctype html><html><body>secret</body></html>',
    ]);

    PlanningServer::tool(DeleteMockup::class, ['id' => $otherMockup->id])
        ->assertHasErrors();

    expect(SpecMockup::withoutGlobalScopes()->find($otherMockup->id))->not->toBeNull();
});
