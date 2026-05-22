<?php

use App\Mcp\Prompts\CaptureIntent;
use App\Mcp\Prompts\PlanSlice;
use App\Mcp\Servers\IntakeServer;
use App\Mcp\Servers\PlanningServer;
use App\Mcp\Tools\Plan\ListWorkItems;
use App\Models\DesignElement;
use App\Models\DesignView;
use App\Models\Project;
use App\Models\User;
use App\Models\WorkItem;
use Laravel\Passport\Passport;

beforeEach(function () {
    $this->user = User::factory()->create();
    Passport::actingAs($this->user, ['mcp:use']);

    $this->project = Project::create([
        'workspace_id' => $this->user->active_workspace_id,
        'name' => 'Architecture-aware delivery',
        'rigor_level' => 2,
    ]);

    $this->view = DesignView::create([
        'project_id' => $this->project->id,
        'viewpoint' => 'logical',
        'name' => 'Logical services',
        'description' => 'Service boundaries and responsibilities.',
    ]);

    DesignElement::create([
        'design_view_id' => $this->view->id,
        'kind' => 'entity',
        'name' => 'Workflow service',
        'type' => 'service',
        'purpose' => 'Coordinates work-item lifecycle decisions.',
        'properties' => ['owner' => 'delivery'],
    ]);
});

it('points planning prompts at existing architecture context', function () {
    PlanningServer::prompt(PlanSlice::class, [
        'project_id' => $this->project->id,
    ])->assertOk()
        ->assertSee('Architecture views: 1')
        ->assertSee('Architecture elements: 1')
        ->assertSee('Architecture content is agent-facing design context')
        ->assertSee('list-architecture-views')
        ->assertSee('list-architecture-elements')
        ->assertSee('trace-query');
});

it('includes architecture context guidance when listing work items for implementation', function () {
    WorkItem::create([
        'project_id' => $this->project->id,
        'kind' => 'task',
        'name' => 'Implement lifecycle guard',
    ]);

    PlanningServer::tool(ListWorkItems::class, [
        'project_id' => $this->project->id,
    ])->assertOk()
        ->assertStructuredContent(function ($json) {
            $json->where('architecture_context.available', true)
                ->where('architecture_context.views.0.id', $this->view->id)
                ->where('architecture_context.views.0.name', 'Logical services')
                ->where('architecture_context.views.0.elements_count', 1)
                ->where('architecture_context.tools', ['list-architecture-views', 'list-architecture-elements', 'trace-query'])
                ->has('results', 1)
                ->etc();
        });
});

it('tells intent capture to inspect existing context before generating artifacts', function () {
    IntakeServer::prompt(CaptureIntent::class, [
        'project_id' => $this->project->id,
    ])->assertOk()
        ->assertSee('When generating a new intent artifact')
        ->assertSee('stakeholders, concerns, sources, requirements, and citations')
        ->assertSee('Do not invent missing human intent');
});

it('advertises context-first guidance on artifact-generating tools', function () {
    $tools = $this->postJson('/mcp/all', [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/list',
        'params' => ['per_page' => 300],
    ])->assertOk()->json('result.tools');

    $descriptions = collect($tools)->mapWithKeys(fn (array $tool): array => [
        $tool['name'] => $tool['description'] ?? '',
    ]);

    expect($descriptions['upsert-requirements'])->toContain('inspect relevant stakeholders, concerns, sources, citations, and existing requirements')
        ->and($descriptions['upsert-plan'])->toContain('inspect project intent, requirements, architecture context, risks, roles, milestones, and existing work items')
        ->and($descriptions['upsert-work-items'])->toContain('implementation brief')
        ->and($descriptions['upsert-verification-cases'])->toContain('verification brief')
        ->and($descriptions['upsert-change-request'])->toContain('change impact brief')
        ->and($descriptions['upsert-review'])->toContain('review brief')
        ->and($descriptions['upsert-architecture-view'])->toContain('inspect stakeholders, concerns, requirements, existing views/elements, and source citations')
        ->and($descriptions['upsert-architecture-elements'])->toContain('inspect the parent view, addressed concerns, related requirements, existing elements, and source citations')
        ->and($descriptions['upsert-mockup'])->toContain('mockup design brief');
});
