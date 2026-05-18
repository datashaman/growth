<?php

use App\Mcp\Servers\PlanningServer;
use App\Mcp\Tools\Plan\ListMockupRevisions;
use App\Mcp\Tools\Plan\RevertMockup;
use App\Models\Project;
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

    $this->mockup = createMockup($this->workItem->id, 'Checkout layout', '<html><body>v1</body></html>');
    $this->mockup->appendRevision('<html><body>v2</body></html>');
});

it('lists a mockup revisions', function () {
    PlanningServer::tool(ListMockupRevisions::class, ['mockup_id' => $this->mockup->id])
        ->assertOk()
        ->assertStructuredContent(function ($json) {
            $json->where('mockup_id', $this->mockup->id)
                ->where('total', 2)
                ->where('current_revision', 2)
                ->where('results.0.number', 1)
                ->where('results.1.number', 2)
                ->etc();
        });
});

it('reverts a mockup by appending the chosen revision as a new latest', function () {
    PlanningServer::tool(RevertMockup::class, [
        'mockup_id' => $this->mockup->id,
        'revision' => 1,
    ])
        ->assertOk()
        ->assertStructuredContent(function ($json) {
            $json->where('reverted_to', 1)
                ->where('revision', 3)
                ->etc();
        });

    $revisions = $this->mockup->revisions()->get();
    expect($revisions)->toHaveCount(3)
        ->and($revisions->last()->html)->toContain('v1');
});

it('errors when reverting to a revision the mockup does not have', function () {
    PlanningServer::tool(RevertMockup::class, [
        'mockup_id' => $this->mockup->id,
        'revision' => 9,
    ])->assertHasErrors();

    expect($this->mockup->revisions()->count())->toBe(2);
});

it('does not list revisions for a mockup in another workspace', function () {
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
    $otherMockup = createMockup($otherItem->id, 'Their layout', '<html><body>secret</body></html>');

    PlanningServer::tool(ListMockupRevisions::class, ['mockup_id' => $otherMockup->id])
        ->assertHasErrors();
});
