<?php

use App\Mcp\Servers\PlanningServer;
use App\Mcp\Tools\Plan\DeleteMockup;
use App\Mcp\Tools\Plan\DeleteOwnerMockups;
use App\Mcp\Tools\Plan\ListMockups;
use App\Mcp\Tools\Plan\ListProjectMockups;
use App\Models\Project;
use App\Models\Requirement;
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
    createMockup($this->workItem, 'Roomy layout', '<!doctype html><html><body>roomy</body></html>');
    createMockup($this->workItem, 'Compact layout', '<!doctype html><html><body>compact</body></html>');

    PlanningServer::tool(ListMockups::class, [
        'owner_type' => 'work_item',
        'owner_id' => $this->workItem->id,
    ])
        ->assertOk()
        ->assertStructuredContent(function ($json) {
            $json->where('total', 2)
                ->where('results.0.name', 'Compact layout')
                ->where('results.1.name', 'Roomy layout')
                ->etc();
        });
});

it('lists a requirement mockups', function () {
    $requirement = Requirement::create([
        'project_id' => $this->project->id,
        'doc' => 'srs',
        'type' => 'functional',
        'text' => 'The checkout must be one page',
    ]);
    createMockup($requirement, 'One-page checkout', '<!doctype html><html><body>one page</body></html>');

    PlanningServer::tool(ListMockups::class, [
        'owner_type' => 'requirement',
        'owner_id' => $requirement->id,
    ])
        ->assertOk()
        ->assertStructuredContent(function ($json) {
            $json->where('total', 1)
                ->where('results.0.name', 'One-page checkout')
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

    PlanningServer::tool(ListMockups::class, [
        'owner_type' => 'work_item',
        'owner_id' => $otherItem->id,
    ])->assertHasErrors();
});

it('deletes a mockup', function () {
    $mockup = createMockup($this->workItem, 'Roomy layout', '<!doctype html><html><body>roomy</body></html>');

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
    $otherMockup = createMockup($otherItem, 'Their layout', '<!doctype html><html><body>secret</body></html>');

    PlanningServer::tool(DeleteMockup::class, ['id' => $otherMockup->id])
        ->assertHasErrors();

    expect(SpecMockup::withoutGlobalScopes()->find($otherMockup->id))->not->toBeNull();
});

it('lists mockup coverage across a project with filters', function () {
    $covered = WorkItem::create([
        'project_id' => $this->project->id,
        'kind' => 'task',
        'name' => 'Covered UI',
        'status' => 'in_progress',
        'needs_mockups' => true,
    ]);
    $missing = WorkItem::create([
        'project_id' => $this->project->id,
        'kind' => 'task',
        'name' => 'Missing UI',
        'status' => 'todo',
        'needs_mockups' => true,
    ]);
    createMockup($covered, 'Ready state', '<!doctype html><html><body>ready</body></html>');
    Requirement::create([
        'project_id' => $this->project->id,
        'doc' => 'srs',
        'type' => 'usability',
        'text' => 'The dashboard shall render a usable empty state.',
        'renders_ui' => true,
    ]);

    PlanningServer::tool(ListProjectMockups::class, [
        'project_id' => $this->project->id,
        'owner_type' => 'work_item',
        'needs_mockups' => true,
        'missing_mockups' => true,
    ])
        ->assertOk()
        ->assertStructuredContent(function ($json) use ($missing) {
            $json->where('total', 1)
                ->where('results.0.owner_type', 'work_item')
                ->where('results.0.owner_id', $missing->id)
                ->where('results.0.reference', $missing->reference())
                ->where('results.0.status', 'todo')
                ->where('results.0.needs_mockups', true)
                ->where('results.0.missing_mockups', true)
                ->etc();
        });

    PlanningServer::tool(ListProjectMockups::class, [
        'project_id' => $this->project->id,
        'work_item_status' => 'in_progress',
    ])
        ->assertOk()
        ->assertStructuredContent(function ($json) use ($covered) {
            $json->where('total', 1)
                ->where('results.0.owner_id', $covered->id)
                ->where('results.0.mockups.0.name', 'Ready state')
                ->etc();
        });
});

it('does not list project mockups from another workspace', function () {
    $other = User::factory()->create();
    $otherProject = Project::create([
        'workspace_id' => $other->active_workspace_id,
        'name' => 'Theirs',
        'rigor_level' => 2,
    ]);

    PlanningServer::tool(ListProjectMockups::class, [
        'project_id' => $otherProject->id,
    ])->assertHasErrors();
});

it('deletes all mockups for one owner and reports removed revisions', function () {
    $first = createMockup($this->workItem, 'Roomy layout', '<!doctype html><html><body>roomy</body></html>');
    $first->appendRevision('<!doctype html><html><body>roomy v2</body></html>');
    createMockup($this->workItem, 'Compact layout', '<!doctype html><html><body>compact</body></html>');

    $otherItem = WorkItem::create([
        'project_id' => $this->project->id,
        'kind' => 'task',
        'name' => 'Other owner',
    ]);
    $otherMockup = createMockup($otherItem, 'Keep me', '<!doctype html><html><body>keep</body></html>');

    PlanningServer::tool(DeleteOwnerMockups::class, [
        'owner_type' => 'work_item',
        'owner_id' => $this->workItem->id,
    ])
        ->assertOk()
        ->assertStructuredContent(function ($json) {
            $json->where('owner_id', $this->workItem->id)
                ->where('deleted_mockups', 2)
                ->where('deleted_revisions', 3)
                ->etc();
        });

    expect($this->workItem->mockups()->count())->toBe(0)
        ->and(SpecMockup::find($otherMockup->id))->not->toBeNull();
});

it('rejects owner mockup cleanup for a missing owner', function () {
    PlanningServer::tool(DeleteOwnerMockups::class, [
        'owner_type' => 'work_item',
        'owner_id' => '01HX0000000000000000000000',
    ])->assertHasErrors();
});

it('does not cleanup owner mockups from another workspace', function () {
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
    $otherMockup = createMockup($otherItem, 'Their layout', '<!doctype html><html><body>secret</body></html>');

    PlanningServer::tool(DeleteOwnerMockups::class, [
        'owner_type' => 'work_item',
        'owner_id' => $otherItem->id,
    ])->assertHasErrors();

    expect(SpecMockup::withoutGlobalScopes()->find($otherMockup->id))->not->toBeNull();
});
