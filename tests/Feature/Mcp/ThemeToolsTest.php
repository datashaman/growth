<?php

use App\Mcp\Servers\PlanningServer;
use App\Mcp\Servers\ReadonlyServer;
use App\Mcp\Tools\Plan\DeleteTheme;
use App\Mcp\Tools\Plan\GetTheme;
use App\Mcp\Tools\Plan\ListThemeAssignments;
use App\Mcp\Tools\Plan\ListThemes;
use App\Mcp\Tools\Plan\UpsertTheme;
use App\Mcp\Tools\Plan\UpsertThemeAssignment;
use App\Models\Project;
use App\Models\Theme;
use App\Models\ThemeAssignment;
use App\Models\User;
use App\Models\WorkItem;
use Laravel\Passport\Passport;

beforeEach(function () {
    $this->user = User::factory()->create();
    Passport::actingAs($this->user, ['mcp:use']);

    $this->project = Project::create([
        'workspace_id' => $this->user->active_workspace_id,
        'name' => 'Themes',
        'rigor_level' => 2,
    ]);
});

it('creates lists fetches and deletes themes through MCP', function () {
    PlanningServer::tool(UpsertTheme::class, [
        'project_id' => $this->project->id,
        'name' => 'Mission Control',
        'slug' => 'mission-control',
        'description' => 'Dense operational console.',
        'design_notes' => 'Use crisp panels and high-signal status colour.',
        'css_tokens' => ['surface' => '#101418', 'accent' => '#22c55e'],
        'raw_css' => 'body { background: var(--surface); color: white; }',
        'is_default' => true,
    ])
        ->assertOk()
        ->assertStructuredContent(function ($json) {
            $json->where('name', 'Mission Control')
                ->where('slug', 'mission-control')
                ->where('is_default', true)
                ->where('created', true)
                ->etc();
        });

    $theme = Theme::where('project_id', $this->project->id)->sole();

    ReadonlyServer::tool(ListThemes::class, ['project_id' => $this->project->id])
        ->assertOk()
        ->assertStructuredContent(function ($json) use ($theme) {
            $json->where('default_theme_id', $theme->id)
                ->where('themes.0.slug', 'mission-control')
                ->where('themes.0.css_token_count', 2)
                ->etc();
        });

    ReadonlyServer::tool(GetTheme::class, ['id' => $theme->id])
        ->assertOk()
        ->assertStructuredContent(function ($json) use ($theme) {
            $json->where('resources.theme_uri', "growth://themes/{$theme->id}")
                ->where('resources.css_uri', "growth://themes/{$theme->id}/css")
                ->where('preview_specimen.selectors.0.role', 'preview_chrome')
                ->where('preview_specimen.sample_html', fn (string $html): bool => str_contains($html, 'data-preview-role="title"'))
                ->missing('raw_css')
                ->missing('compiled_css')
                ->etc();
        });

    readResource(ReadonlyServer::class, "growth://themes/{$theme->id}")
        ->assertOk()
        ->assertSee('"type":"theme"')
        ->assertSee('"uri":"growth://themes/'.$theme->id.'/css"')
        ->assertDontSee('body { background');

    readResource(ReadonlyServer::class, "growth://themes/{$theme->id}/css")
        ->assertOk()
        ->assertSee('--surface: #101418;')
        ->assertSee('body { background: var(--surface); color: white; }');

    PlanningServer::tool(DeleteTheme::class, ['id' => $theme->id])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json->where('deleted', true)->etc());

    expect(Theme::count())->toBe(0);
});

it('registers theme tools using the standard resource names', function () {
    $planningTools = collect($this->postJson('/mcp/planning', [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/list',
        'params' => ['per_page' => 300],
    ])->assertOk()->json('result.tools'))->pluck('name');

    $readonlyTools = collect($this->postJson('/mcp/readonly', [
        'jsonrpc' => '2.0',
        'id' => 2,
        'method' => 'tools/list',
        'params' => ['per_page' => 300],
    ])->assertOk()->json('result.tools'))->pluck('name');

    expect($planningTools->all())->toContain('list-themes', 'get-theme', 'upsert-theme', 'delete-theme', 'list-theme-assignments', 'upsert-theme-assignment')
        ->and($readonlyTools->all())->toContain('list-themes', 'get-theme', 'list-theme-assignments')
        ->and($planningTools->all())->not->toContain('list-project-themes', 'get-project-theme', 'upsert-project-theme', 'delete-project-theme')
        ->and($readonlyTools->all())->not->toContain('list-project-themes', 'get-project-theme');
});

it('exposes theme resource templates', function () {
    $resources = $this->postJson('/mcp/readonly', [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'resources/templates/list',
        'params' => ['per_page' => 300],
    ])->assertOk()->json('result.resourceTemplates');

    expect(collect($resources)->pluck('uriTemplate')->all())
        ->toContain('growth://themes/{theme}')
        ->toContain('growth://themes/{theme}/css');
});

it('creates and lists scoped theme assignments through MCP', function () {
    $theme = Theme::create([
        'project_id' => $this->project->id,
        'name' => 'Vendor Warmth',
        'slug' => 'vendor-warmth',
    ]);

    PlanningServer::tool(UpsertThemeAssignment::class, [
        'project_id' => $this->project->id,
        'theme_id' => $theme->id,
        'scope_type' => 'vendor',
        'scope_key' => 'acme',
        'label' => 'Acme Foods',
        'notes' => 'Use for Acme profile and product cards.',
    ])
        ->assertOk()
        ->assertStructuredContent(function ($json) use ($theme) {
            $json->where('theme_id', $theme->id)
                ->where('theme_slug', 'vendor-warmth')
                ->where('scope_type', 'vendor')
                ->where('scope_key', 'acme')
                ->where('created', true)
                ->etc();
        });

    ReadonlyServer::tool(ListThemeAssignments::class, ['project_id' => $this->project->id])
        ->assertOk()
        ->assertStructuredContent(function ($json) {
            $json->where('total', 1)
                ->where('assignments.0.scope_type', 'vendor')
                ->where('assignments.0.scope_key', 'acme')
                ->where('assignments.0.theme_slug', 'vendor-warmth')
                ->etc();
        });
});

it('creates and lists canonical mockup theme assignments through MCP', function () {
    $theme = Theme::create([
        'project_id' => $this->project->id,
        'name' => 'Buyer Market',
        'slug' => 'buyer-market',
    ]);
    $workItem = WorkItem::create([
        'project_id' => $this->project->id,
        'kind' => 'deliverable',
        'name' => 'Checkout',
    ]);
    $mockup = createMockup($workItem, 'Checkout layout', '<!doctype html><html><body>checkout</body></html>');

    PlanningServer::tool(UpsertThemeAssignment::class, [
        'project_id' => $this->project->id,
        'theme_id' => $theme->id,
        'scope_type' => 'mockup',
        'scope_key' => $mockup->id,
        'label' => 'Checkout preview',
    ])
        ->assertOk()
        ->assertStructuredContent(function ($json) use ($mockup) {
            $json->where('scope_type', 'mockup')
                ->where('scope_key', $mockup->id)
                ->where('target.mockup_id', $mockup->id)
                ->where('target.mockup_uri', "growth://mockups/{$mockup->id}")
                ->where('created', true)
                ->etc();
        });

    ReadonlyServer::tool(ListThemeAssignments::class, ['project_id' => $this->project->id, 'scope_type' => 'mockup'])
        ->assertOk()
        ->assertStructuredContent(function ($json) use ($mockup) {
            $json->where('total', 1)
                ->where('assignments.0.scope_type', 'mockup')
                ->where('assignments.0.scope_key', $mockup->id)
                ->where('assignments.0.target.mockup_id', $mockup->id)
                ->where('assignments.0.target.mockup_uri', "growth://mockups/{$mockup->id}")
                ->where('assignments.0.target.preview_url', route('mockups.show', $mockup))
                ->etc();
        });
});

it('rejects mockup theme assignments for non-mockup keys', function () {
    $theme = Theme::create([
        'project_id' => $this->project->id,
        'name' => 'Buyer Market',
        'slug' => 'buyer-market',
    ]);

    PlanningServer::tool(UpsertThemeAssignment::class, [
        'project_id' => $this->project->id,
        'theme_id' => $theme->id,
        'scope_type' => 'mockup',
        'scope_key' => 'wi-007-vendor-application-public',
    ])->assertHasErrors(['mockup scope key must be an existing mockup ULID']);

    expect(ThemeAssignment::count())->toBe(0);
});

it('rejects mockup theme assignments for mockups in another project', function () {
    $theme = Theme::create([
        'project_id' => $this->project->id,
        'name' => 'Buyer Market',
        'slug' => 'buyer-market',
    ]);
    $foreignProject = Project::create([
        'workspace_id' => $this->user->active_workspace_id,
        'name' => 'Foreign',
        'rigor_level' => 2,
    ]);
    $foreignWorkItem = WorkItem::create([
        'project_id' => $foreignProject->id,
        'kind' => 'deliverable',
        'name' => 'Foreign checkout',
    ]);
    $foreignMockup = createMockup($foreignWorkItem, 'Foreign layout', '<!doctype html><html><body>foreign</body></html>');

    PlanningServer::tool(UpsertThemeAssignment::class, [
        'project_id' => $this->project->id,
        'theme_id' => $theme->id,
        'scope_type' => 'mockup',
        'scope_key' => $foreignMockup->id,
    ])->assertHasErrors(['mockup scope key must be an existing mockup ULID in the assignment project']);

    expect(ThemeAssignment::count())->toBe(0);
});

it('rejects theme assignments that reference a theme from another project', function () {
    $other = User::factory()->create();
    $foreignProject = Project::create([
        'workspace_id' => $other->active_workspace_id,
        'name' => 'Foreign',
        'rigor_level' => 2,
    ]);
    $foreignTheme = Theme::withoutGlobalScopes()->create([
        'project_id' => $foreignProject->id,
        'name' => 'Foreign',
        'slug' => 'foreign',
    ]);

    PlanningServer::tool(UpsertThemeAssignment::class, [
        'project_id' => $this->project->id,
        'theme_id' => $foreignTheme->id,
        'scope_type' => 'site_section',
        'scope_key' => 'home',
    ])->assertHasErrors(['selected theme id is invalid']);

    expect(ThemeAssignment::count())->toBe(0);
});

it('keeps one default theme per project', function () {
    $first = Theme::create([
        'project_id' => $this->project->id,
        'name' => 'First',
        'slug' => 'first',
        'is_default' => true,
    ]);

    PlanningServer::tool(UpsertTheme::class, [
        'project_id' => $this->project->id,
        'name' => 'Second',
        'slug' => 'second',
        'is_default' => true,
    ])->assertOk();

    expect($first->fresh()->is_default)->toBeFalse()
        ->and(Theme::where('project_id', $this->project->id)->where('is_default', true)->value('slug'))->toBe('second');
});

it('rejects remote theme css', function () {
    PlanningServer::tool(UpsertTheme::class, [
        'project_id' => $this->project->id,
        'name' => 'Remote',
        'slug' => 'remote',
        'raw_css' => '@import url("https://example.com/theme.css");',
    ])->assertHasErrors(['Theme CSS must be self-contained']);
});

it('warns without rejecting themes with low contrast preview text', function () {
    PlanningServer::tool(UpsertTheme::class, [
        'project_id' => $this->project->id,
        'name' => 'Low Contrast',
        'slug' => 'low-contrast',
        'css_tokens' => [
            'surface' => '#111111',
            'text' => '#181818',
        ],
    ])
        ->assertOk()
        ->assertStructuredContent(function ($json) {
            $json->where('warnings.0.code', 'preview_text_contrast')
                ->where('created', true)
                ->etc();
        });

    expect(Theme::where('project_id', $this->project->id)->where('slug', 'low-contrast')->exists())->toBeTrue();
});

it('does not warn for readable theme preview text', function () {
    PlanningServer::tool(UpsertTheme::class, [
        'project_id' => $this->project->id,
        'name' => 'Readable',
        'slug' => 'readable',
        'css_tokens' => [
            'surface' => '#111111',
            'text' => '#f8fafc',
        ],
    ])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json->where('warnings', [])->etc());
});

it('does not expose themes from another workspace', function () {
    $other = User::factory()->create();
    $foreignProject = Project::create([
        'workspace_id' => $other->active_workspace_id,
        'name' => 'Foreign',
        'rigor_level' => 2,
    ]);
    $foreignTheme = Theme::create([
        'project_id' => $foreignProject->id,
        'name' => 'Secret',
        'slug' => 'secret',
    ]);

    ReadonlyServer::tool(GetTheme::class, ['id' => $foreignTheme->id])
        ->assertHasErrors(['selected id is invalid']);
});
