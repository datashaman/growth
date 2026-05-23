<?php

use App\Mcp\Servers\PlanningServer;
use App\Mcp\Servers\ReadonlyServer;
use App\Mcp\Tools\Plan\DeleteProjectTheme;
use App\Mcp\Tools\Plan\GetProjectTheme;
use App\Mcp\Tools\Plan\ListProjectThemes;
use App\Mcp\Tools\Plan\UpsertProjectTheme;
use App\Models\Project;
use App\Models\ProjectTheme;
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

it('creates lists fetches and deletes project themes through MCP', function () {
    PlanningServer::tool(UpsertProjectTheme::class, [
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

    $theme = ProjectTheme::where('project_id', $this->project->id)->sole();

    ReadonlyServer::tool(ListProjectThemes::class, ['project_id' => $this->project->id])
        ->assertOk()
        ->assertStructuredContent(function ($json) use ($theme) {
            $json->where('default_theme_id', $theme->id)
                ->where('themes.0.slug', 'mission-control')
                ->where('themes.0.css_token_count', 2)
                ->etc();
        });

    ReadonlyServer::tool(GetProjectTheme::class, ['id' => $theme->id])
        ->assertOk()
        ->assertStructuredContent(function ($json) {
            $json->where('compiled_css', fn (string $css): bool => str_contains($css, '--surface: #101418;'))
                ->etc();
        });

    PlanningServer::tool(DeleteProjectTheme::class, ['id' => $theme->id])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json->where('deleted', true)->etc());

    expect(ProjectTheme::count())->toBe(0);
});

it('keeps one default theme per project', function () {
    $first = ProjectTheme::create([
        'project_id' => $this->project->id,
        'name' => 'First',
        'slug' => 'first',
        'is_default' => true,
    ]);

    PlanningServer::tool(UpsertProjectTheme::class, [
        'project_id' => $this->project->id,
        'name' => 'Second',
        'slug' => 'second',
        'is_default' => true,
    ])->assertOk();

    expect($first->fresh()->is_default)->toBeFalse()
        ->and(ProjectTheme::where('project_id', $this->project->id)->where('is_default', true)->value('slug'))->toBe('second');
});

it('rejects remote theme css', function () {
    PlanningServer::tool(UpsertProjectTheme::class, [
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
    $foreignTheme = ProjectTheme::create([
        'project_id' => $foreignProject->id,
        'name' => 'Secret',
        'slug' => 'secret',
    ]);

    ReadonlyServer::tool(GetProjectTheme::class, ['id' => $foreignTheme->id])
        ->assertHasErrors(['selected id is invalid']);
});
