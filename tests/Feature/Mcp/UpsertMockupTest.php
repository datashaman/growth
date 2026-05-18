<?php

use App\Mcp\Servers\PlanningServer;
use App\Mcp\Tools\Plan\UpsertMockup;
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

it('stores a mockup for a work item', function () {
    PlanningServer::tool(UpsertMockup::class, [
        'work_item_id' => $this->workItem->id,
        'name' => 'Checkout layout',
        'html' => '<!doctype html><html><body><h1>Checkout</h1></body></html>',
    ])
        ->assertOk()
        ->assertStructuredContent(function ($json) {
            $json->where('name', 'Checkout layout')
                ->where('revision', 1)
                ->where('created', true)
                ->etc();
        });

    $mockups = SpecMockup::where('work_item_id', $this->workItem->id)->get();
    expect($mockups)->toHaveCount(1)
        ->and($mockups->first()->currentRevision->html)->toContain('<h1>Checkout</h1>');
});

it('adds a second mockup under a new name', function () {
    $args = [
        'work_item_id' => $this->workItem->id,
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

    expect(SpecMockup::where('work_item_id', $this->workItem->id)->pluck('name')->sort()->values()->all())
        ->toBe(['Compact layout', 'Roomy layout']);
});

it('appends a revision when upserted under an existing name', function () {
    $args = [
        'work_item_id' => $this->workItem->id,
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
    $mockups = SpecMockup::where('work_item_id', $this->workItem->id)->get();
    expect($mockups)->toHaveCount(1)
        ->and($mockups->first()->revisions)->toHaveCount(2)
        ->and($mockups->first()->currentRevision->html)->toContain('v2');
});
