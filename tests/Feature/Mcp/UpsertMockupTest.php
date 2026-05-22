<?php

use App\Mcp\Servers\PlanningServer;
use App\Mcp\Tools\Plan\UpsertMockup;
use App\Models\DesignElement;
use App\Models\DesignView;
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

it('stores a mockup for a work item', function () {
    PlanningServer::tool(UpsertMockup::class, [
        'owner_type' => 'work_item',
        'owner_id' => $this->workItem->id,
        'name' => 'Checkout layout',
        'html' => '<!doctype html><html><body><h1>Checkout</h1></body></html>',
    ])
        ->assertOk()
        ->assertStructuredContent(function ($json) {
            $json->where('name', 'Checkout layout')
                ->where('owner_type', 'work_item')
                ->where('revision', 1)
                ->where('created', true)
                ->etc();
        });

    $mockups = SpecMockup::where('owner_id', $this->workItem->id)->get();
    expect($mockups)->toHaveCount(1)
        ->and($mockups->first()->currentRevision->html)->toContain('<h1>Checkout</h1>');
});

it('returns architecture guidance when creating a mockup in a project with design context', function () {
    $view = DesignView::create([
        'project_id' => $this->project->id,
        'viewpoint' => 'interaction',
        'name' => 'Checkout interaction',
        'description' => 'How checkout panels exchange state.',
    ]);

    DesignElement::create([
        'design_view_id' => $view->id,
        'kind' => 'entity',
        'name' => 'Checkout panel',
        'type' => 'ui_component',
        'purpose' => 'Collects payment and shipping intent.',
    ]);

    PlanningServer::tool(UpsertMockup::class, [
        'owner_type' => 'work_item',
        'owner_id' => $this->workItem->id,
        'name' => 'Checkout layout',
        'html' => '<!doctype html><html><body><h1>Checkout</h1></body></html>',
    ])
        ->assertOk()
        ->assertStructuredContent(function ($json) use ($view) {
            $json->where('design_brief.architecture_available', true)
                ->where('design_brief.uri', "growth://owners/work_item/{$this->workItem->id}/mockup-design-brief")
                ->where('design_brief.architecture_views.0.id', $view->id)
                ->where('design_brief.architecture_views.0.name', 'Checkout interaction')
                ->where('design_brief.architecture_views.0.elements_count', 1)
                ->etc();
        });
});

it('stores a mockup for a requirement', function () {
    $requirement = Requirement::create([
        'project_id' => $this->project->id,
        'doc' => 'srs',
        'type' => 'functional',
        'text' => 'The checkout must be one page',
    ]);

    PlanningServer::tool(UpsertMockup::class, [
        'owner_type' => 'requirement',
        'owner_id' => $requirement->id,
        'name' => 'One-page checkout',
        'html' => '<!doctype html><html><body>one page</body></html>',
    ])
        ->assertOk()
        ->assertStructuredContent(function ($json) use ($requirement) {
            $json->where('owner_type', 'requirement')
                ->where('owner_id', $requirement->id)
                ->where('revision', 1)
                ->where('created', true)
                ->etc();
        });

    expect($requirement->mockups()->count())->toBe(1);
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

    PlanningServer::tool(UpsertMockup::class, [
        'owner_type' => 'work_item',
        'owner_id' => $otherItem->id,
        'name' => 'Checkout layout',
        'html' => '<!doctype html><html><body>x</body></html>',
    ])->assertHasErrors();
});

it('rejects a requirement from another workspace', function () {
    $other = User::factory()->create();
    $otherProject = Project::create([
        'workspace_id' => $other->active_workspace_id,
        'name' => 'Theirs',
        'rigor_level' => 2,
    ]);
    $otherRequirement = Requirement::create([
        'project_id' => $otherProject->id,
        'doc' => 'srs',
        'type' => 'functional',
        'text' => 'Their requirement',
    ]);

    PlanningServer::tool(UpsertMockup::class, [
        'owner_type' => 'requirement',
        'owner_id' => $otherRequirement->id,
        'name' => 'Checkout layout',
        'html' => '<!doctype html><html><body>x</body></html>',
    ])->assertHasErrors();
});

it('adds a second mockup under a new name', function () {
    $args = [
        'owner_type' => 'work_item',
        'owner_id' => $this->workItem->id,
        'name' => 'Roomy layout',
        'html' => '<!doctype html><html><body>roomy</body></html>',
    ];

    PlanningServer::tool(UpsertMockup::class, $args)->assertOk();
    PlanningServer::tool(UpsertMockup::class, [
        ...$args,
        'name' => 'Compact layout',
        'html' => '<!doctype html><html><body>compact</body></html>',
    ])
        ->assertOk()
        ->assertStructuredContent(function ($json) {
            $json->where('name', 'Compact layout')
                ->where('created', true)
                ->etc();
        });

    expect($this->workItem->mockups()->pluck('name')->sort()->values()->all())
        ->toBe(['Compact layout', 'Roomy layout']);
});

it('updates the default mockup in place when no name is supplied', function () {
    $args = [
        'owner_type' => 'work_item',
        'owner_id' => $this->workItem->id,
        'html' => '<!doctype html><html><body>v1</body></html>',
    ];

    PlanningServer::tool(UpsertMockup::class, $args)
        ->assertOk()
        ->assertStructuredContent(function ($json) {
            $json->where('name', 'default')
                ->where('revision', 1)
                ->where('created', true)
                ->etc();
        });

    $firstId = SpecMockup::sole()->id;

    PlanningServer::tool(UpsertMockup::class, [
        ...$args,
        'html' => '<!doctype html><html><body>v2</body></html>',
    ])
        ->assertOk()
        ->assertStructuredContent(function ($json) use ($firstId) {
            $json->where('id', $firstId)
                ->where('name', 'default')
                ->where('revision', 2)
                ->where('created', false)
                ->etc();
        });

    $mockups = $this->workItem->mockups()->get();
    expect($mockups)->toHaveCount(1)
        ->and($mockups->first()->revisions)->toHaveCount(2)
        ->and($mockups->first()->currentRevision->html)->toContain('v2');
});

it('treats an explicit name=default the same as omitting name', function () {
    $args = [
        'owner_type' => 'work_item',
        'owner_id' => $this->workItem->id,
        'html' => '<!doctype html><html><body>v1</body></html>',
    ];

    PlanningServer::tool(UpsertMockup::class, $args)->assertOk();
    $implicitId = SpecMockup::sole()->id;

    PlanningServer::tool(UpsertMockup::class, [
        ...$args,
        'name' => 'default',
        'html' => '<!doctype html><html><body>v2</body></html>',
    ])
        ->assertOk()
        ->assertStructuredContent(function ($json) use ($implicitId) {
            $json->where('id', $implicitId)
                ->where('created', false)
                ->etc();
        });

    expect($this->workItem->mockups()->count())->toBe(1);
});

it('keeps the default mockup and a named alternative as separate rows', function () {
    PlanningServer::tool(UpsertMockup::class, [
        'owner_type' => 'work_item',
        'owner_id' => $this->workItem->id,
        'html' => '<!doctype html><html><body>default</body></html>',
    ])->assertOk();

    PlanningServer::tool(UpsertMockup::class, [
        'owner_type' => 'work_item',
        'owner_id' => $this->workItem->id,
        'name' => 'Compact layout',
        'html' => '<!doctype html><html><body>compact</body></html>',
    ])->assertOk();

    expect($this->workItem->mockups()->pluck('name')->sort()->values()->all())
        ->toBe(['Compact layout', 'default']);
});

it('appends a revision when upserted under an existing name', function () {
    $args = [
        'owner_type' => 'work_item',
        'owner_id' => $this->workItem->id,
        'name' => 'Roomy layout',
        'html' => '<!doctype html><html><body>v1</body></html>',
    ];

    PlanningServer::tool(UpsertMockup::class, $args)->assertOk();
    PlanningServer::tool(UpsertMockup::class, [
        ...$args,
        'html' => '<!doctype html><html><body>v2</body></html>',
    ])
        ->assertOk()
        ->assertStructuredContent(function ($json) {
            $json->where('name', 'Roomy layout')
                ->where('revision', 2)
                ->where('created', false)
                ->etc();
        });

    // One mockup, two revisions retained — its current state is the latest.
    $mockups = $this->workItem->mockups()->get();
    expect($mockups)->toHaveCount(1)
        ->and($mockups->first()->revisions)->toHaveCount(2)
        ->and($mockups->first()->currentRevision->html)->toContain('v2');
});
