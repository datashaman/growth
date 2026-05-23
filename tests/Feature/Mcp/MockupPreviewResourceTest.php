<?php

use App\Mcp\Servers\PlanningServer;
use App\Mcp\Servers\ReadonlyServer;
use App\Models\Project;
use App\Models\Theme;
use App\Models\User;
use App\Models\WorkItem;
use Illuminate\Support\Facades\URL;
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
        ->toContain('growth://mockups/{mockup}/{revision}/html')
        ->toContain('growth://mockups/{mockup}/{revision}/preview')
        ->toContain('growth://mockups/{mockup}/{revision}/preview?theme={theme}')
        ->toContain('growth://mockups/{mockup}/{revision}/screenshot')
        ->toContain('growth://mockups/{mockup}/{revision}/screenshot?theme={theme}');
});

it('returns current mockup metadata with preview uri and screenshot asset url', function () {
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
    $screenshotUrl = URL::signedRoute('mockups.screenshot', [
        'mockup' => $mockup->id,
        'revision' => $revision->id,
        'theme' => 'assigned',
    ]);
    $resourceUri = "growth://mockups/{$mockup->id}/{$revision->id}/screenshot?theme=assigned";

    readResource(ReadonlyServer::class, "growth://mockups/{$mockup->id}")
        ->assertOk()
        ->assertSee('"type":"mockup"')
        ->assertSee('"id":"'.$mockup->id.'"')
        ->assertSee('"id":"'.$revision->id.'"')
        ->assertSee('"uri":"growth://mockups/'.$mockup->id.'/'.$revision->id.'/html"')
        ->assertSee('"uri":"growth://mockups/'.$mockup->id.'/'.$revision->id.'/preview"')
        ->assertSee('"asset":{"url":"'.$screenshotUrl.'"')
        ->assertSee('"resource_uri":"'.$resourceUri.'"')
        ->assertSee('"mime_type":"image/png"')
        ->assertDontSee('Customer dashboard')
        ->assertDontSee('WI-003 implementation note')
        ->assertDontSee('"base64"')
        ->assertDontSee('"blob"')
        ->assertSee("growth://mockups/{$mockup->id}/{$revision->id}/screenshot?theme=assigned");

    $this->get($screenshotUrl)
        ->assertOk()
        ->assertHeader('Content-Type', 'image/png');

    readResource(ReadonlyServer::class, "growth://mockups/{$mockup->id}/{$revision->id}/preview")
        ->assertOk()
        ->assertSee('Customer dashboard')
        ->assertSee('Ready for review')
        ->assertSee('data-growth-theme="mission-control"', false);
});

it('returns specific mockup revision metadata', function () {
    $mockup = createMockup($this->workItem, 'default', '<!doctype html><html><body>First draft</body></html>');
    $first = $mockup->currentRevision;
    $second = $mockup->appendRevision('<!doctype html><html><body>Second draft</body></html>');

    readResource(ReadonlyServer::class, "growth://mockups/{$mockup->id}/{$first->id}")
        ->assertOk()
        ->assertSee('"type":"mockup_revision"')
        ->assertSee('"id":"'.$first->id.'"')
        ->assertSee("growth://mockups/{$mockup->id}/{$first->id}/html")
        ->assertSee("growth://mockups/{$mockup->id}/{$first->id}/preview")
        ->assertSee('/mockups/'.$mockup->id.'/revisions/'.$first->id.'/screenshot.png')
        ->assertDontSee('First draft')
        ->assertDontSee('Second draft');

    readResource(ReadonlyServer::class, "growth://mockups/{$mockup->id}/{$second->id}")
        ->assertOk()
        ->assertSee('"id":"'.$second->id.'"')
        ->assertSee("growth://mockups/{$mockup->id}/{$second->id}/html")
        ->assertSee("growth://mockups/{$mockup->id}/{$second->id}/preview")
        ->assertSee('/mockups/'.$mockup->id.'/revisions/'.$second->id.'/screenshot.png')
        ->assertDontSee('Second draft')
        ->assertDontSee('First draft');

    readResource(ReadonlyServer::class, "growth://mockups/{$mockup->id}/{$first->id}/preview")
        ->assertOk()
        ->assertSee('First draft')
        ->assertDontSee('Second draft');
});

it('returns screenshot pixels through the inspectable asset url and MCP resource', function () {
    $mockup = createMockup($this->workItem, 'default', '<!doctype html><html><body>Screenshot me</body></html>');
    $revision = $mockup->currentRevision;
    $screenshotUrl = URL::signedRoute('mockups.screenshot', [
        'mockup' => $mockup->id,
        'revision' => $revision->id,
        'theme' => 'assigned',
    ]);

    $this->get($screenshotUrl)
        ->assertOk()
        ->assertHeader('Content-Type', 'image/png');

    readResource(ReadonlyServer::class, "growth://mockups/{$mockup->id}/{$revision->id}/screenshot?theme=assigned")
        ->assertOk()
        ->assertSee('iVBORw0KGgo');
});

it('supports disabling theme rendering for preview resources', function () {
    Theme::create([
        'project_id' => $this->project->id,
        'name' => 'Mission Control',
        'slug' => 'mission-control',
        'raw_css' => 'body { color: red; }',
        'is_default' => true,
    ]);
    $mockup = createMockup($this->workItem, 'default', '<!doctype html><html><body>No theme labels</body></html>');
    $revision = $mockup->currentRevision;

    readResource(ReadonlyServer::class, "growth://mockups/{$mockup->id}/{$revision->id}/preview?theme=none")
        ->assertOk()
        ->assertSee('No theme labels')
        ->assertDontSee('data-growth-theme="mission-control"', false);

    $screenshotUrl = URL::signedRoute('mockups.screenshot', [
        'mockup' => $mockup->id,
        'revision' => $revision->id,
        'theme' => 'none',
    ]);
    $resourceUri = "growth://mockups/{$mockup->id}/{$revision->id}/screenshot?theme=none";

    readResource(ReadonlyServer::class, "growth://mockups/{$mockup->id}/{$revision->id}?theme=none")
        ->assertOk()
        ->assertSee('"theme":"none"')
        ->assertSee($screenshotUrl)
        ->assertSee($resourceUri);

    $this->get($screenshotUrl)
        ->assertOk()
        ->assertHeader('Content-Type', 'image/png');

    readResource(ReadonlyServer::class, $resourceUri)
        ->assertOk()
        ->assertSee('iVBORw0KGgo');
});

it('errors for revisions that do not belong to the mockup', function () {
    $mockup = createMockup($this->workItem, 'default', '<!doctype html><html><body>one</body></html>');
    $other = createMockup($this->workItem, 'Other', '<!doctype html><html><body>two</body></html>');

    readResource(ReadonlyServer::class, "growth://mockups/{$mockup->id}/{$other->currentRevision->id}")
        ->assertHasErrors(['not found on mockup']);
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
        'name' => 'Private',
    ]);
    $mockup = createMockup($otherItem, 'default', '<!doctype html><html><body>secret</body></html>');
    $revisionId = $mockup->revisions()->withoutGlobalScopes()->first()->id;

    readResource(ReadonlyServer::class, "growth://mockups/{$mockup->id}/{$revisionId}")
        ->assertHasErrors(['not found']);

    readResource(ReadonlyServer::class, "growth://mockups/{$mockup->id}/{$revisionId}/screenshot?theme=assigned")
        ->assertHasErrors(['not found']);
});

it('is available on the planning server too', function () {
    $mockup = createMockup($this->workItem, 'default', '<!doctype html><html><body>Planning visible</body></html>');
    $revision = $mockup->currentRevision;

    readResource(PlanningServer::class, "growth://mockups/{$mockup->id}/{$revision->id}/preview")
        ->assertOk()
        ->assertSee('Planning visible');
});
