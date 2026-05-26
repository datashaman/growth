<?php

use App\Mcp\Servers\ArchitectureServer;
use App\Mcp\Servers\ReadonlyServer;
use App\Mcp\Tools\Architecture\ListArchitectureViewpoints;
use App\Models\CustomViewpoint;
use App\Models\DesignView;
use App\Models\Project;
use App\Models\User;
use Laravel\Passport\Passport;

beforeEach(function () {
    $this->user = User::factory()->create();
    Passport::actingAs($this->user, ['mcp:use']);

    $this->project = Project::create([
        'workspace_id' => $this->user->active_workspace_id,
        'name' => 'Architecture viewpoint discovery',
        'rigor_level' => 2,
    ]);
});

it('lists built-in and custom architecture viewpoints with explicit origin markers', function () {
    $custom = CustomViewpoint::create([
        'project_id' => $this->project->id,
        'name' => 'regulatory',
        'concerns' => ['compliance'],
        'element_types' => ['control'],
        'languages' => ['prose'],
        'source' => 'project governance',
    ]);

    ArchitectureServer::tool(ListArchitectureViewpoints::class, [
        'project_id' => $this->project->id,
    ])->assertOk()
        ->assertStructuredContent(function ($json) use ($custom) {
            $json->where('built_in', DesignView::BUILTIN_VIEWPOINTS)
                ->where('results.0.name', DesignView::BUILTIN_VIEWPOINTS[0])
                ->where('results.0.type', 'built_in')
                ->where('results.0.builtin', true)
                ->where('results.0.id', null)
                ->where('results.0.source', 'Growth built-in viewpoint vocabulary')
                ->where('results.'.count(DesignView::BUILTIN_VIEWPOINTS).'.id', $custom->id)
                ->where('results.'.count(DesignView::BUILTIN_VIEWPOINTS).'.name', 'regulatory')
                ->where('results.'.count(DesignView::BUILTIN_VIEWPOINTS).'.type', 'custom')
                ->where('results.'.count(DesignView::BUILTIN_VIEWPOINTS).'.builtin', false)
                ->where('results.'.count(DesignView::BUILTIN_VIEWPOINTS).'.source', 'project governance')
                ->etc();
        });
});

it('filters built-in and custom viewpoints by name', function () {
    CustomViewpoint::create([
        'project_id' => $this->project->id,
        'name' => 'customer-interface',
        'concerns' => ['usability'],
        'element_types' => ['journey'],
        'languages' => ['wireflow'],
    ]);

    ReadonlyServer::tool(ListArchitectureViewpoints::class, [
        'project_id' => $this->project->id,
        'q' => 'interface',
    ])->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('results.0.name', 'interface')
            ->where('results.0.type', 'built_in')
            ->where('results.1.name', 'customer-interface')
            ->where('results.1.type', 'custom')
            ->missing('results.2')
            ->etc());
});

it('advertises built-in viewpoint discovery from architecture tool descriptions', function () {
    $tools = $this->postJson('/mcp/architecture', [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/list',
        'params' => ['per_page' => 300],
    ])->assertOk()->json('result.tools');

    $descriptions = collect($tools)->mapWithKeys(fn (array $tool): array => [
        $tool['name'] => $tool['description'] ?? '',
    ]);

    expect($descriptions['list-architecture-viewpoints'])
        ->toContain('including Growth built-ins and project custom viewpoints')
        ->and($descriptions['upsert-architecture-view'])
        ->toContain('built-ins returned by list-architecture-viewpoints');
});
