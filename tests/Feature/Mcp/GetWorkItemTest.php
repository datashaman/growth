<?php

use App\Mcp\Servers\PlanningServer;
use App\Mcp\Servers\ReadonlyServer;
use App\Mcp\Tools\Plan\GetWorkItem;
use App\Models\Project;
use App\Models\Requirement;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkItem;
use App\Models\WorkItemDeliveryLink;
use Laravel\Passport\Passport;

beforeEach(function () {
    $this->user = User::factory()->create();
    Passport::actingAs($this->user, ['mcp:use']);

    $this->project = Project::create([
        'workspace_id' => $this->user->active_workspace_id,
        'name' => 'Detail reads',
        'rigor_level' => 2,
    ]);
});

it('returns full work item details by id', function () {
    $parent = WorkItem::create([
        'project_id' => $this->project->id,
        'kind' => 'deliverable',
        'name' => 'Telemetry work',
    ]);
    $role = Role::create([
        'project_id' => $this->project->id,
        'name' => 'Engineering Lead',
    ]);
    $consulted = Role::create([
        'project_id' => $this->project->id,
        'name' => 'Security',
    ]);
    $requirement = Requirement::create([
        'project_id' => $this->project->id,
        'doc' => 'srs',
        'type' => 'functional',
        'text' => 'The dashboard shall show telemetry health.',
        'priority' => 'high',
    ]);
    $milestone = $this->project->milestones()->create([
        'name' => 'Telemetry beta',
        'status' => 'pending',
    ]);
    $dependency = WorkItem::create([
        'project_id' => $this->project->id,
        'kind' => 'task',
        'name' => 'Prepare telemetry API',
    ]);
    $workItem = WorkItem::create([
        'project_id' => $this->project->id,
        'parent_id' => $parent->id,
        'responsible_role_id' => $role->id,
        'kind' => 'task',
        'name' => 'Build telemetry dashboard',
        'description' => 'Render operational telemetry.',
        'needs_mockups' => true,
    ]);
    $child = WorkItem::create([
        'project_id' => $this->project->id,
        'parent_id' => $workItem->id,
        'kind' => 'task',
        'name' => 'Add telemetry filters',
    ]);
    $workItem->requirements()->attach($requirement->id);
    $workItem->milestones()->attach($milestone->id);
    $workItem->dependencies()->attach($dependency->id);
    $workItem->raciRoles()->attach($role->id, ['raci' => 'a']);
    $workItem->raciRoles()->attach($consulted->id, ['raci' => 'c']);
    WorkItemDeliveryLink::create([
        'work_item_id' => $workItem->id,
        'type' => 'pull_request',
        'ref' => '#42',
        'url' => 'https://github.com/datashaman/growth/pull/42',
        'description' => 'Implementation PR',
    ]);
    createMockup($workItem, 'default', '<html></html>');

    PlanningServer::tool(GetWorkItem::class, ['id' => $workItem->id])
        ->assertOk()
        ->assertStructuredContent(function ($json) use ($workItem, $parent, $role, $consulted, $requirement, $milestone, $dependency, $child) {
            $json->where('id', $workItem->id)
                ->where('reference', $workItem->reference())
                ->where('name', 'Build telemetry dashboard')
                ->where('description', 'Render operational telemetry.')
                ->where('needs_mockups', true)
                ->where('parent.id', $parent->id)
                ->where('children.0.id', $child->id)
                ->where('responsible_role.id', $role->id)
                ->where('raci.0.role_id', $role->id)
                ->where('raci.0.raci', 'a')
                ->where('raci.1.role_id', $consulted->id)
                ->where('requirements.0.id', $requirement->id)
                ->where('requirements.0.reference', $requirement->reference())
                ->where('milestones.0.id', $milestone->id)
                ->where('dependencies.0.id', $dependency->id)
                ->where('delivery_links.0.ref', '#42')
                ->where('mockups.0.name', 'default')
                ->where('implementation_brief', "growth://work-items/{$workItem->id}/implementation-brief")
                ->etc();
        });
});

it('returns a work item by project reference through the readonly surface', function () {
    $workItem = WorkItem::create([
        'project_id' => $this->project->id,
        'kind' => 'task',
        'name' => 'Reference lookup',
    ]);

    ReadonlyServer::tool(GetWorkItem::class, [
        'project_id' => $this->project->id,
        'reference' => 'WI-'.$workItem->number,
    ])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('id', $workItem->id)
            ->where('reference', $workItem->reference())
            ->etc());
});

it('errors for a missing work item reference', function () {
    PlanningServer::tool(GetWorkItem::class, [
        'project_id' => $this->project->id,
        'reference' => 'WI-999',
    ])->assertHasErrors(['No work item matching']);
});

it('does not return a work item from another workspace', function () {
    $other = User::factory()->create();
    $foreignProject = Project::create([
        'workspace_id' => $other->active_workspace_id,
        'name' => 'Foreign',
        'rigor_level' => 2,
    ]);
    $foreignItem = WorkItem::create([
        'project_id' => $foreignProject->id,
        'kind' => 'task',
        'name' => 'Foreign task',
    ]);

    PlanningServer::tool(GetWorkItem::class, ['id' => $foreignItem->id])
        ->assertHasErrors(['No work item matching']);
});
