<?php

use App\Mcp\Servers\PlanningServer;
use App\Mcp\Servers\ReadonlyServer;
use App\Mcp\Tools\Plan\DeleteTheme;
use App\Mcp\Tools\Plan\GetTheme;
use App\Mcp\Tools\Plan\ListThemes;
use App\Mcp\Tools\Plan\UpsertTheme;
use App\Models\Project;
use App\Models\Theme;
use App\Models\User;
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
        ->assertStructuredContent(function ($json) {
            $json->where('compiled_css', fn (string $css): bool => str_contains($css, '--surface: #101418;'))
                ->etc();
        });

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

    expect($planningTools->all())->toContain('list-themes', 'get-theme', 'upsert-theme', 'delete-theme')
        ->and($readonlyTools->all())->toContain('list-themes', 'get-theme')
        ->and($planningTools->all())->not->toContain('list-project-themes', 'get-project-theme', 'upsert-project-theme', 'delete-project-theme')
        ->and($readonlyTools->all())->not->toContain('list-project-themes', 'get-project-theme');
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
