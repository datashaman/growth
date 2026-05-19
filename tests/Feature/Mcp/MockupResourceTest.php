<?php

use App\Mcp\Servers\ReadonlyServer;
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
});

it('serves a mockup current revision html', function () {
    $mockup = createMockup($this->workItem, 'Roomy layout', '<!doctype html><html><body>roomy</body></html>');
    $mockup->appendRevision('<!doctype html><html><body>latest</body></html>');

    readResource(ReadonlyServer::class, "growth://mockups/{$mockup->id}")
        ->assertOk()
        ->assertSee('latest')
        ->assertDontSee('roomy');
});

it('errors for an unknown mockup id', function () {
    readResource(ReadonlyServer::class, 'growth://mockups/01jzzzzzzzzzzzzzzzzzzzzzzz')
        ->assertHasErrors(['not found']);
});

it('does not serve a mockup from another workspace', function () {
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

    readResource(ReadonlyServer::class, "growth://mockups/{$otherMockup->id}")
        ->assertHasErrors(['not found']);
});
