<?php

use App\Models\Project;
use App\Models\Theme;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    $this->project = Project::create([
        'workspace_id' => $this->user->active_workspace_id,
        'name' => 'Theme project',
        'rigor_level' => 2,
    ]);

    session(['selected_project_id' => $this->project->id]);
});

it('displays themes without web crud controls', function () {
    $theme = Theme::create([
        'project_id' => $this->project->id,
        'name' => 'Mission Control',
        'slug' => 'mission-control',
        'description' => 'Dense operational console.',
        'design_notes' => 'Compact panels, status-first colour.',
        'css_tokens' => [
            'surface' => '#101418',
            'surface-muted' => '#1f2937',
            'panel' => '#f8fbff',
            'panel-muted' => '#dbeafe',
            'text' => '#0f172a',
            'accent' => '#22c55e',
            'accent-strong' => '#15803d',
            'warning' => '#f59e0b',
        ],
        'raw_css' => '.panel { box-shadow: 0 20px 40px rgba(21, 128, 61, .2); }',
        'is_default' => true,
    ]);

    $this->get(route('themes'))
        ->assertOk()
        ->assertSee('Mission Control')
        ->assertSee('mission-control')
        ->assertSee('default')
        ->assertSee('--surface')
        ->assertSee('data-test="theme-preview"', false)
        ->assertSee('data-growth-theme-preview', false)
        ->assertSee('--surface: #101418;', false)
        ->assertSee('.panel { box-shadow: 0 20px 40px rgba(21, 128, 61, .2); }', false)
        ->assertSee('Interface sample')
        ->assertSee('Primary action')
        ->assertSee('Form controls')
        ->assertSee('Success state')
        ->assertSee(route('themes.show', $theme), false)
        ->assertSee('Inspect')
        ->assertDontSee('Festival Market')
        ->assertSee('Compact panels')
        ->assertDontSee('Save theme')
        ->assertDontSee('Delete theme');
});

it('shows a theme detail preview specimen with compiled css', function () {
    $theme = Theme::create([
        'project_id' => $this->project->id,
        'name' => 'Mission Control',
        'slug' => 'mission-control',
        'description' => 'Dense operational console.',
        'design_notes' => 'Compact panels, status-first colour.',
        'css_tokens' => [
            'surface' => '#101418',
            'text' => '#f8fafc',
            'accent' => '#22c55e',
        ],
        'raw_css' => '.panel { box-shadow: 0 20px 40px rgba(21, 128, 61, .2); }',
        'is_default' => true,
    ]);

    $this->get(route('themes.show', $theme))
        ->assertOk()
        ->assertSee('Mission Control')
        ->assertSee('Back to themes')
        ->assertSee('Preview specimen')
        ->assertSee('data-test="theme-detail-preview"', false)
        ->assertSee('data-growth-theme-preview', false)
        ->assertSee('data-preview-role=&quot;title&quot;', false)
        ->assertSee('data-preview-role=&quot;primary-action&quot;', false)
        ->assertSee('data-preview-role=&quot;form&quot;', false)
        ->assertSee('data-preview-role=&quot;badge&quot;', false)
        ->assertSee('Interface sample')
        ->assertSee('Primary action')
        ->assertSee('Secondary action')
        ->assertSee('Form controls')
        ->assertSee('Project name')
        ->assertSee('Status')
        ->assertSee('Badges')
        ->assertSee('Success state')
        ->assertSee('Attention state')
        ->assertSee('List content')
        ->assertSee('--surface: #101418;', false)
        ->assertSee('.panel { box-shadow: 0 20px 40px rgba(21, 128, 61, .2); }', false)
        ->assertSee('Dense operational console.')
        ->assertSee('Compact panels, status-first colour.')
        ->assertDontSee('Save theme')
        ->assertDontSee('Delete theme');
});

it('does not expose theme details from another workspace', function () {
    $other = User::factory()->create();
    $foreignProject = Project::create([
        'workspace_id' => $other->active_workspace_id,
        'name' => 'Foreign theme project',
        'rigor_level' => 2,
    ]);
    $theme = Theme::create([
        'project_id' => $foreignProject->id,
        'name' => 'Foreign Theme',
        'slug' => 'foreign-theme',
    ]);

    $this->get(route('themes.show', $theme))
        ->assertNotFound();
});

it('shows an MCP-oriented empty state', function () {
    $this->get(route('themes'))
        ->assertOk()
        ->assertSee('No themes have been created for this project yet.')
        ->assertSee('MCP theme tools');
});
