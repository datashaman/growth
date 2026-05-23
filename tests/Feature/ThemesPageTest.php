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
    Theme::create([
        'project_id' => $this->project->id,
        'name' => 'Mission Control',
        'slug' => 'mission-control',
        'description' => 'Dense operational console.',
        'design_notes' => 'Compact panels, status-first colour.',
        'css_tokens' => ['surface' => '#101418'],
        'raw_css' => 'body { background: var(--surface); }',
        'is_default' => true,
    ]);

    $this->get(route('themes'))
        ->assertOk()
        ->assertSee('Mission Control')
        ->assertSee('mission-control')
        ->assertSee('default')
        ->assertSee('--surface')
        ->assertSee('Compact panels')
        ->assertDontSee('Save theme')
        ->assertDontSee('Delete theme');
});

it('shows an MCP-oriented empty state', function () {
    $this->get(route('themes'))
        ->assertOk()
        ->assertSee('No themes have been created for this project yet.')
        ->assertSee('MCP theme tools');
});
