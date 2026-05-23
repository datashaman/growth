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

it('exposes mockup metadata html and preview resource templates', function () {
    $resources = $this->postJson('/mcp/readonly', [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'resources/templates/list',
        'params' => ['per_page' => 300],
    ])->assertOk()->json('result.resourceTemplates');

    expect(collect($resources)->pluck('uriTemplate')->all())
        ->toContain('growth://mockups/{mockup}')
        ->toContain('growth://mockups/{mockup}/{revision}')
        ->toContain('growth://mockups/{mockup}/{revision}/html')
        ->toContain('growth://mockups/{mockup}/{revision}/preview')
        ->toContain('growth://mockups/{mockup}/{revision}/preview?theme={theme}')
        ->toContain('growth://mockups/{mockup}/{revision}/screenshot')
        ->toContain('growth://mockups/{mockup}/{revision}/screenshot?theme={theme}');
});

it('serves mockup current revision metadata with html and preview resources', function () {
    $mockup = createMockup($this->workItem, 'Roomy layout', '<!doctype html><html><body>roomy</body></html>');
    $mockup->appendRevision('<!doctype html><html><body>latest</body></html>');
    $revision = $mockup->fresh('currentRevision')->currentRevision;

    readResource(ReadonlyServer::class, "growth://mockups/{$mockup->id}")
        ->assertOk()
        ->assertSee('"type":"mockup"')
        ->assertSee('"id":"'.$mockup->id.'"')
        ->assertSee('"number":2')
        ->assertSee("growth://mockups/{$mockup->id}/{$revision->id}/html")
        ->assertSee("growth://mockups/{$mockup->id}/{$revision->id}/preview")
        ->assertSee("growth://mockups/{$mockup->id}/{$revision->id}/screenshot?theme=assigned")
        ->assertSee('/mockups/'.$mockup->id.'/revisions/'.$revision->id.'/screenshot.png')
        ->assertDontSee('latest')
        ->assertDontSee('roomy');
});

it('serves specific mockup revision metadata with html and preview resources', function () {
    $mockup = createMockup($this->workItem, 'Roomy layout', '<!doctype html><html><body>roomy</body></html>');
    $mockup->appendRevision('<!doctype html><html><body>latest</body></html>');
    $first = $mockup->revisions()->where('number', 1)->firstOrFail();

    readResource(ReadonlyServer::class, "growth://mockups/{$mockup->id}/{$first->id}")
        ->assertOk()
        ->assertSee('"type":"mockup_revision"')
        ->assertSee('"number":1')
        ->assertSee("growth://mockups/{$mockup->id}/{$first->id}/html")
        ->assertSee("growth://mockups/{$mockup->id}/{$first->id}/preview")
        ->assertSee("growth://mockups/{$mockup->id}/{$first->id}/screenshot?theme=assigned")
        ->assertSee('/mockups/'.$mockup->id.'/revisions/'.$first->id.'/screenshot.png')
        ->assertDontSee('roomy');
});

it('serves raw html and preview html as separate resources', function () {
    $mockup = createMockup($this->workItem, 'Roomy layout', '<!doctype html><html><body><a href="/next">roomy</a></body></html>');
    $revision = $mockup->currentRevision;

    readResource(ReadonlyServer::class, "growth://mockups/{$mockup->id}/{$revision->id}/html")
        ->assertOk()
        ->assertSee('<a href="/next">roomy</a>', false);

    readResource(ReadonlyServer::class, "growth://mockups/{$mockup->id}/{$revision->id}/preview")
        ->assertOk()
        ->assertSee('roomy')
        ->assertSee('data-growth-preview-inert');
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
