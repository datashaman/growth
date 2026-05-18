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
                ->where('created', true)
                ->etc();
        });

    expect(SpecMockup::where('work_item_id', $this->workItem->id)->count())->toBe(1);
});

it('replaces the work item mockup on a second call', function () {
    $args = [
        'work_item_id' => $this->workItem->id,
        'name' => 'First draft',
        'html' => '<!doctype html><html><body>v1</body></html>',
    ];

    PlanningServer::tool(UpsertMockup::class, $args)->assertOk();
    PlanningServer::tool(UpsertMockup::class, [
        ...$args,
        'name' => 'Second draft',
        'html' => '<!doctype html><html><body>v2</body></html>',
    ])
        ->assertOk()
        ->assertStructuredContent(function ($json) {
            $json->where('name', 'Second draft')
                ->where('created', false)
                ->etc();
        });

    $mockups = SpecMockup::where('work_item_id', $this->workItem->id)->get();
    expect($mockups)->toHaveCount(1)
        ->and($mockups->first()->html)->toContain('v2');
});
