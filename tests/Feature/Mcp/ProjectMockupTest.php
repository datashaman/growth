<?php

use App\Mcp\Servers\PlanningServer;
use App\Mcp\Tools\Plan\UpsertProjectMockup;
use App\Models\Mockup;
use App\Models\Project;
use App\Models\Theme;
use App\Models\User;
use App\Models\WorkItem;
use App\Support\MockupPreview;
use Laravel\Passport\Passport;

beforeEach(function () {
    $this->user = User::factory()->create();
    Passport::actingAs($this->user, ['mcp:use']);

    $this->project = Project::create([
        'workspace_id' => $this->user->active_workspace_id,
        'name' => 'Design System Test',
        'rigor_level' => 2,
    ]);
});

it('creates a layout mockup for a project', function () {
    $html = '<!doctype html><html><body><nav>App Nav</nav><div id="growth-content"></div></body></html>';

    PlanningServer::tool(UpsertProjectMockup::class, [
        'project_id' => $this->project->id,
        'name' => 'layout',
        'html' => $html,
    ])
        ->assertOk()
        ->assertStructuredContent(function ($json) {
            $json->where('name', 'layout')
                ->where('project_id', $this->project->id)
                ->where('revision', 1)
                ->where('created', true)
                ->where('warnings', [])
                ->where('resources.list_uri', "growth://projects/{$this->project->id}/mockups")
                ->where('resources.html_uri', "growth://projects/{$this->project->id}/mockups/layout")
                ->etc();
        });

    $mockup = Mockup::where('owner_type', 'project')->where('owner_id', $this->project->id)->sole();
    expect($mockup->name)->toBe('layout')
        ->and($mockup->currentRevision->html)->toContain('growth-content');
});

it('creates a component specimen for a project', function () {
    PlanningServer::tool(UpsertProjectMockup::class, [
        'project_id' => $this->project->id,
        'name' => 'forms',
        'html' => '<!doctype html><html><body><form><input type="text"><button>Submit</button></form></body></html>',
    ])
        ->assertOk()
        ->assertStructuredContent(function ($json) {
            $json->where('name', 'forms')
                ->where('created', true)
                ->where('warnings', [])
                ->etc();
        });
});

it('warns when layout is missing the content slot', function () {
    PlanningServer::tool(UpsertProjectMockup::class, [
        'project_id' => $this->project->id,
        'name' => 'layout',
        'html' => '<!doctype html><html><body><nav>No slot here</nav></body></html>',
    ])
        ->assertOk()
        ->assertStructuredContent(function ($json) {
            $json->where('warnings.0.code', 'missing_content_slot')->etc();
        });
});

it('warns when html references external assets', function () {
    PlanningServer::tool(UpsertProjectMockup::class, [
        'project_id' => $this->project->id,
        'name' => 'typography',
        'html' => '<!doctype html><html><head><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter"></head><body></body></html>',
    ])
        ->assertOk()
        ->assertStructuredContent(function ($json) {
            $json->where('warnings.0.code', 'external_assets')->etc();
        });
});

it('appends a new revision on re-upsert', function () {
    PlanningServer::tool(UpsertProjectMockup::class, [
        'project_id' => $this->project->id,
        'name' => 'layout',
        'html' => '<!doctype html><html><body><div id="growth-content"></div></body></html>',
    ])->assertOk();

    PlanningServer::tool(UpsertProjectMockup::class, [
        'project_id' => $this->project->id,
        'name' => 'layout',
        'html' => '<!doctype html><html><body><nav>New nav</nav><div id="growth-content"></div></body></html>',
    ])
        ->assertOk()
        ->assertStructuredContent(function ($json) {
            $json->where('revision', 2)->where('created', false)->etc();
        });

    expect(Mockup::where('owner_type', 'project')->where('owner_id', $this->project->id)->count())->toBe(1);
});

it('injects page mockup content into the project layout at preview time', function () {
    $layoutHtml = '<!doctype html><html><body><nav id="chrome">App Nav</nav><div id="growth-content"></div></body></html>';
    $layout = Mockup::firstOrCreate([
        'owner_type' => 'project',
        'owner_id' => $this->project->id,
        'name' => 'layout',
    ]);
    $layout->appendRevision($layoutHtml);

    $workItem = WorkItem::create([
        'project_id' => $this->project->id,
        'kind' => WorkItem::KINDS[0],
        'name' => 'Dashboard',
    ]);
    $pageMockup = Mockup::firstOrCreate([
        'owner_type' => 'work_item',
        'owner_id' => $workItem->id,
        'name' => 'default',
    ]);
    $pageRevision = $pageMockup->appendRevision('<html><body><h1>Dashboard Content</h1></body></html>');

    $preview = app(MockupPreview::class)->html($pageMockup, $pageRevision);

    expect($preview)
        ->toContain('id="chrome"')
        ->toContain('<h1>Dashboard Content</h1>')
        ->toContain('id="growth-content"');
});

it('skips layout injection when no layout mockup exists', function () {
    $workItem = WorkItem::create([
        'project_id' => $this->project->id,
        'kind' => WorkItem::KINDS[0],
        'name' => 'Settings',
    ]);
    $pageMockup = Mockup::firstOrCreate([
        'owner_type' => 'work_item',
        'owner_id' => $workItem->id,
        'name' => 'default',
    ]);
    $pageRevision = $pageMockup->appendRevision('<html><body><h1>Settings</h1></body></html>');

    $preview = app(MockupPreview::class)->html($pageMockup, $pageRevision);

    expect($preview)->toContain('<h1>Settings</h1>')->not->toContain('growth-content');
});

it('skips layout injection for project-owned mockups', function () {
    $layoutHtml = '<!doctype html><html><body><nav>Nav</nav><div id="growth-content"></div></body></html>';
    $layout = Mockup::firstOrCreate([
        'owner_type' => 'project',
        'owner_id' => $this->project->id,
        'name' => 'layout',
    ]);
    $layoutRevision = $layout->appendRevision($layoutHtml);

    $preview = app(MockupPreview::class)->html($layout, $layoutRevision);

    expect($preview)->toContain('growth-content')->toContain('<nav>Nav</nav>');
    // The layout itself is not wrapped in another layout
    expect(substr_count($preview, 'growth-content'))->toBe(1);
});

it('applies a theme to a project-owned design system mockup', function () {
    $theme = Theme::create([
        'project_id' => $this->project->id,
        'name' => 'Festival Ops',
        'slug' => 'festival-ops',
        'is_default' => true,
        'css_tokens' => ['--fm-bg' => '#f9f6f1'],
    ]);

    $mockup = Mockup::firstOrCreate([
        'owner_type' => 'project',
        'owner_id' => $this->project->id,
        'name' => 'typography',
    ]);
    $revision = $mockup->appendRevision('<!doctype html><html><head></head><body><h1>Type</h1></body></html>');

    $preview = app(MockupPreview::class)->html($mockup, $revision, 'festival-ops');

    expect($preview)->toContain('--fm-bg');
});

it('injects resolved context variables when context is passed to MockupPreview', function () {
    Theme::create([
        'project_id' => $this->project->id,
        'name' => 'Festival Ops',
        'slug' => 'festival-ops',
        'is_default' => true,
        'css_tokens' => [
            '--fm-surface-inset' => '#eee7da',
            '--surface-muted' => 'var(--fm-surface-inset)',
            '--elevation-0' => 'none',
            '--radius-tight' => '4px',
            '--spacing-inner-tight' => '10px',
        ],
    ]);

    $mockup = Mockup::firstOrCreate([
        'owner_type' => 'project',
        'owner_id' => $this->project->id,
        'name' => 'form-specimen',
    ]);
    $revision = $mockup->appendRevision('<!doctype html><html><head></head><body><form></form></body></html>');

    $preview = app(MockupPreview::class)->html(
        $mockup, $revision, 'festival-ops',
        ['state' => 'disabled', 'density' => 'compact'],
    );

    // state:disabled wins surface + elevation; density:compact wins radius + spacing_inner
    expect($preview)
        ->toContain('data-growth-context')
        ->toContain('--surface: var(--surface-muted)')
        ->toContain('--elevation: var(--elevation-0)')
        ->toContain('--radius: var(--radius-tight)')
        ->toContain('--spacing-inner: var(--spacing-inner-tight)');
});
