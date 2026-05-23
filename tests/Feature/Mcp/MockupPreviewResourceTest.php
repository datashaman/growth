<?php

use App\Mcp\Servers\PlanningServer;
use App\Mcp\Servers\ReadonlyServer;
use App\Models\Project;
use App\Models\Theme;
use App\Models\User;
use App\Models\WorkItem;
use Laravel\Passport\Passport;

beforeEach(function () {
    $this->user = User::factory()->create();
    Passport::actingAs($this->user, ['mcp:use']);

    $this->project = Project::create([
        'workspace_id' => $this->user->active_workspace_id,
        'name' => 'Mockup Previews',
        'rigor_level' => 2,
    ]);

    $this->workItem = WorkItem::create([
        'project_id' => $this->project->id,
        'kind' => WorkItem::KINDS[0],
        'name' => 'Inspect the artifact',
    ]);
});

it('exposes mockup preview resources as mcp resource templates', function () {
    $resources = $this->postJson('/mcp/readonly', [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'resources/templates/list',
        'params' => ['per_page' => 300],
    ])->assertOk()->json('result.resourceTemplates');

    expect(collect($resources)->pluck('uriTemplate')->all())
        ->toContain('growth://mockups/{mockup}')
        ->toContain('growth://mockups/{mockup}/{revision}')
        ->toContain('growth://mockups/{mockup}/{revision}/screenshot');
});

it('previews the current mockup revision through a browser without inline screenshot data', function () {
    $theme = Theme::create([
        'project_id' => $this->project->id,
        'name' => 'Mission Control',
        'slug' => 'mission-control',
        'raw_css' => 'body { background: rgb(1, 2, 3); }',
        'is_default' => true,
    ]);

    $mockup = createMockup($this->workItem, 'default', <<<'HTML'
<!doctype html>
<html>
<body>
<main>
  <h1>Customer dashboard</h1>
  <p id="status">Ready for review</p>
</main>
<script>
  document.querySelector('#status').textContent = ['WI', '-003'].join('') + ' implementation note';
</script>
</body>
</html>
HTML);

    $revision = $mockup->currentRevision;

    readResource(ReadonlyServer::class, "growth://mockups/{$mockup->id}")
        ->assertOk()
        ->assertSee('"type":"mockup_preview"')
        ->assertSee('"mockup_id":"'.$mockup->id.'"')
        ->assertSee('"revision_id":"'.$revision->id.'"')
        ->assertSee('"revision_number":1')
        ->assertSee(route('mockups.raw', ['mockup' => $mockup, 'revision' => $revision->id, 'theme' => $theme->slug]))
        ->assertSee('"requested":"assigned"')
        ->assertSee('"slug":"mission-control"')
        ->assertSee('Customer dashboard')
        ->assertSee('WI-003 implementation note')
        ->assertSee('"code":"work_item_reference"')
        ->assertSee('"code":"implementation_note"')
        ->assertSee('"uri":"growth://mockups/'.$mockup->id.'/'.$revision->id.'/screenshot"')
        ->assertSee('"mime_type":"image/png"')
        ->assertDontSee('"base64"')
        ->assertDontSee('"blob"');
});

it('inspects a specific mockup revision', function () {
    $mockup = createMockup($this->workItem, 'default', '<!doctype html><html><body>First draft</body></html>');
    $first = $mockup->currentRevision;
    $second = $mockup->appendRevision('<!doctype html><html><body>Second draft</body></html>');

    readResource(ReadonlyServer::class, "growth://mockups/{$mockup->id}/{$first->id}")
        ->assertOk()
        ->assertSee('"revision_id":"'.$first->id.'"')
        ->assertSee('First draft')
        ->assertDontSee('Second draft');

    readResource(ReadonlyServer::class, "growth://mockups/{$mockup->id}/{$second->id}")
        ->assertOk()
        ->assertSee('"revision_id":"'.$second->id.'"')
        ->assertSee('Second draft')
        ->assertDontSee('First draft');
});

it('returns screenshot pixels only through the screenshot resource', function () {
    $mockup = createMockup($this->workItem, 'default', '<!doctype html><html><body>Screenshot me</body></html>');
    $revision = $mockup->currentRevision;

    readResource(ReadonlyServer::class, "growth://mockups/{$mockup->id}/{$revision->id}/screenshot")
        ->assertOk()
        ->assertSee('iVBOR');
});

it('supports disabling theme rendering for inspection', function () {
    Theme::create([
        'project_id' => $this->project->id,
        'name' => 'Mission Control',
        'slug' => 'mission-control',
        'raw_css' => 'body { color: red; }',
        'is_default' => true,
    ]);
    $mockup = createMockup($this->workItem, 'default', '<!doctype html><html><body>No theme labels</body></html>');
    $revision = $mockup->currentRevision;

    readResource(ReadonlyServer::class, "growth://mockups/{$mockup->id}/{$revision->id}?theme=none")
        ->assertOk()
        ->assertSee('"requested":"none"')
        ->assertSee('"resolved":"none"')
        ->assertSee(route('mockups.raw', ['mockup' => $mockup, 'revision' => $revision->id]))
        ->assertDontSee('theme=mission-control');

    readResource(ReadonlyServer::class, "growth://mockups/{$mockup->id}/{$revision->id}/screenshot?theme=none")
        ->assertOk()
        ->assertSee('iVBOR');
});

it('errors for revisions that do not belong to the mockup', function () {
    $mockup = createMockup($this->workItem, 'default', '<!doctype html><html><body>one</body></html>');
    $other = createMockup($this->workItem, 'Other', '<!doctype html><html><body>two</body></html>');

    readResource(ReadonlyServer::class, "growth://mockups/{$mockup->id}/{$other->currentRevision->id}")
        ->assertHasErrors(['not found on mockup']);
});

it('does not inspect a mockup from another workspace', function () {
    $other = User::factory()->create();
    $otherProject = Project::create([
        'workspace_id' => $other->active_workspace_id,
        'name' => 'Theirs',
        'rigor_level' => 2,
    ]);
    $otherItem = WorkItem::create([
        'project_id' => $otherProject->id,
        'kind' => WorkItem::KINDS[0],
        'name' => 'Private',
    ]);
    $mockup = createMockup($otherItem, 'default', '<!doctype html><html><body>secret</body></html>');
    $revisionId = $mockup->revisions()->withoutGlobalScopes()->first()->id;

    readResource(ReadonlyServer::class, "growth://mockups/{$mockup->id}/{$revisionId}")
        ->assertHasErrors(['not found']);
});

it('is available on the planning server too', function () {
    $mockup = createMockup($this->workItem, 'default', '<!doctype html><html><body>Planning visible</body></html>');
    $revision = $mockup->currentRevision;

    readResource(PlanningServer::class, "growth://mockups/{$mockup->id}/{$revision->id}")
        ->assertOk()
        ->assertSee('Planning visible');
});
