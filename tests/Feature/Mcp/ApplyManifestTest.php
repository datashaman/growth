<?php

use App\Mcp\Servers\ManagementServer;
use App\Mcp\Tools\Manifest\ApplyManifest;
use App\Models\Concern;
use App\Models\CustomViewpoint;
use App\Models\DesignElement;
use App\Models\DesignView;
use App\Models\Milestone;
use App\Models\Project;
use App\Models\ProjectPlan;
use App\Models\Requirement;
use App\Models\Role;
use App\Models\Stakeholder;
use App\Models\TestCase;
use App\Models\TestPlan;
use App\Models\User;
use App\Models\WorkItem;
use Laravel\Passport\Passport;

beforeEach(function () {
    $this->user = User::factory()->create();
    Passport::actingAs($this->user, ['mcp:use']);
});

function manifest(array $project = [], array $stakeholders = [], array $concerns = [], array $capabilities = []): array
{
    return [
        'project' => $project + ['name' => 'Imported', 'description' => 'desc', 'rigor_level' => 2, 'status' => 'active'],
        'stakeholders' => $stakeholders,
        'concerns' => $concerns,
        'capabilities' => $capabilities,
    ];
}

it('creates project, stakeholders, concerns and capabilities from scratch in fail mode', function () {
    $response = ManagementServer::tool(ApplyManifest::class, [
        'manifest' => manifest(
            stakeholders: [
                ['slug' => 'pm', 'name' => 'Product Manager', 'role' => 'PM', 'kind' => 'individual'],
            ],
            concerns: [
                ['text' => 'Performance budgets must hold under load.', 'raised_by' => 'pm'],
            ],
            capabilities: [
                ['slug' => 'cap-greeting', 'text' => 'The app shall greet the user on first run.', 'type' => 'functional'],
            ],
        ),
    ]);

    $response->assertOk()->assertStructuredContent(function ($json) {
        $json->has('project_id')
            ->where('mode', 'fail')
            ->where('effective_mode', 'fail')
            ->where('dry_run', false)
            ->where('counts.project_created', true)
            ->where('counts.stakeholders_created', 1)
            ->where('counts.concerns_created', 1)
            ->where('counts.capabilities_created', 1)
            ->has('slugs.capabilities.cap-greeting')
            ->etc();
    });

    expect(Project::where('name', 'Imported')->exists())->toBeTrue();
    $project = Project::where('name', 'Imported')->first();
    expect(Stakeholder::where('project_id', $project->id)->count())->toBe(1);
    expect(Concern::where('project_id', $project->id)->count())->toBe(1);
    expect(Requirement::where('project_id', $project->id)->where('slug', 'cap-greeting')->exists())->toBeTrue();
});

it('rolls back when dry_run is true', function () {
    $response = ManagementServer::tool(ApplyManifest::class, [
        'manifest' => manifest(
            capabilities: [
                ['slug' => 'cap-x', 'text' => 'The thing.', 'type' => 'functional'],
            ],
        ),
        'dry_run' => true,
    ]);

    $response->assertOk()->assertStructuredContent(function ($json) {
        $json->where('dry_run', true)
            ->where('counts.project_created', true)
            ->where('counts.capabilities_created', 1)
            ->etc();
    });

    expect(Project::where('name', 'Imported')->exists())->toBeFalse();
    expect(Requirement::where('slug', 'cap-x')->exists())->toBeFalse();
});

it('fail mode aborts when existing entity differs', function () {
    $project = Project::create(['user_id' => $this->user->id, 'name' => 'Existing', 'rigor_level' => 2]);
    Requirement::create([
        'project_id' => $project->id,
        'slug' => 'cap-a',
        'doc' => 'srs',
        'type' => 'functional',
        'text' => 'Original text.',
    ]);

    $response = ManagementServer::tool(ApplyManifest::class, [
        'manifest' => [
            'project' => ['id' => $project->id, 'name' => 'Existing'],
            'capabilities' => [
                ['slug' => 'cap-a', 'text' => 'Different text.', 'type' => 'functional'],
            ],
        ],
    ]);

    $response->assertHasErrors(['fail mode aborts']);
    expect(Requirement::where('slug', 'cap-a')->first()->text)->toBe('Original text.');
});

it('merge mode updates existing entities by natural key', function () {
    $project = Project::create(['user_id' => $this->user->id, 'name' => 'Existing', 'rigor_level' => 2]);
    Requirement::create([
        'project_id' => $project->id,
        'slug' => 'cap-a',
        'doc' => 'srs',
        'type' => 'functional',
        'text' => 'Original text.',
    ]);

    $response = ManagementServer::tool(ApplyManifest::class, [
        'manifest' => [
            'project' => ['id' => $project->id, 'name' => 'Existing'],
            'capabilities' => [
                ['slug' => 'cap-a', 'text' => 'Updated text.', 'type' => 'functional'],
            ],
        ],
        'mode' => 'merge',
    ]);

    $response->assertOk()->assertStructuredContent(function ($json) {
        $json->where('counts.capabilities_updated', 1)
            ->where('counts.capabilities_created', 0)
            ->etc();
    });

    expect(Requirement::where('slug', 'cap-a')->first()->text)->toBe('Updated text.');
});

it('replace mode requires confirm matching project name', function () {
    $project = Project::create(['user_id' => $this->user->id, 'name' => 'Production', 'rigor_level' => 2]);

    $response = ManagementServer::tool(ApplyManifest::class, [
        'manifest' => [
            'project' => ['id' => $project->id, 'name' => 'Production'],
        ],
        'mode' => 'replace',
    ]);

    $response->assertHasErrors(['confirm']);
});

it('replace mode wipes existing child entities and recreates from manifest', function () {
    $project = Project::create(['user_id' => $this->user->id, 'name' => 'Wipe', 'rigor_level' => 2]);
    Requirement::create([
        'project_id' => $project->id,
        'slug' => 'old-cap',
        'doc' => 'srs',
        'type' => 'functional',
        'text' => 'Old.',
    ]);
    Stakeholder::create(['project_id' => $project->id, 'name' => 'Old Person']);

    $response = ManagementServer::tool(ApplyManifest::class, [
        'manifest' => [
            'project' => ['id' => $project->id, 'name' => 'Wipe'],
            'capabilities' => [
                ['slug' => 'new-cap', 'text' => 'New.', 'type' => 'functional'],
            ],
        ],
        'mode' => 'replace',
        'confirm' => 'Wipe',
    ]);

    $response->assertOk()->assertStructuredContent(function ($json) {
        $json->where('counts.capabilities_deleted', 1)
            ->where('counts.stakeholders_deleted', 1)
            ->where('counts.capabilities_created', 1)
            ->etc();
    });

    expect(Requirement::where('slug', 'old-cap')->exists())->toBeFalse();
    expect(Requirement::where('slug', 'new-cap')->exists())->toBeTrue();
    expect(Stakeholder::where('project_id', $project->id)->where('name', 'Old Person')->exists())->toBeFalse();
});

it('replace mode falls back to fail when project does not exist', function () {
    $response = ManagementServer::tool(ApplyManifest::class, [
        'manifest' => manifest(
            capabilities: [['slug' => 'cap-a', 'text' => 'A capability.', 'type' => 'functional']],
        ),
        'mode' => 'replace',
    ]);

    $response->assertOk()->assertStructuredContent(function ($json) {
        $json->where('mode', 'replace')
            ->where('effective_mode', 'fail')
            ->where('counts.project_created', true)
            ->etc();
    });
});

it('resolves concern raised_by via stakeholder slug declared in the same manifest', function () {
    $response = ManagementServer::tool(ApplyManifest::class, [
        'manifest' => manifest(
            stakeholders: [
                ['slug' => 'ops', 'name' => 'Ops Lead'],
            ],
            concerns: [
                ['text' => 'Pager fatigue.', 'raised_by' => 'ops'],
            ],
        ),
    ]);

    $response->assertOk();
    $stakeholder = Stakeholder::where('name', 'Ops Lead')->first();
    $concern = Concern::where('text', 'Pager fatigue.')->first();
    expect($concern->raised_by_stakeholder_id)->toBe($stakeholder->id);
});

it('reports drift when current updated_at is newer than manifest _exported_at', function () {
    $project = Project::create(['user_id' => $this->user->id, 'name' => 'Drifty', 'rigor_level' => 2]);
    Requirement::create([
        'project_id' => $project->id,
        'slug' => 'cap-a',
        'doc' => 'srs',
        'type' => 'functional',
        'text' => 'Current.',
    ]);

    $response = ManagementServer::tool(ApplyManifest::class, [
        'manifest' => [
            'project' => ['id' => $project->id, 'name' => 'Drifty'],
            'capabilities' => [
                [
                    'slug' => 'cap-a',
                    'text' => 'Current.',
                    'type' => 'functional',
                    '_exported_at' => '2020-01-01T00:00:00Z',
                ],
            ],
        ],
        'mode' => 'merge',
    ]);

    $response->assertOk()->assertStructuredContent(function ($json) {
        $json->has('drift.0')
            ->where('drift.0.entity', 'capability')
            ->where('drift.0.identifier', 'cap-a')
            ->etc();
    });
});

it('rejects manifest with missing capability slug', function () {
    $response = ManagementServer::tool(ApplyManifest::class, [
        'manifest' => manifest(
            capabilities: [['text' => 'No slug here.', 'type' => 'functional']],
        ),
    ]);

    $response->assertHasErrors(['slug']);
});

it('rejects manifest with stakeholder missing name', function () {
    $response = ManagementServer::tool(ApplyManifest::class, [
        'manifest' => manifest(
            stakeholders: [['role' => 'PM']],
        ),
    ]);

    $response->assertHasErrors(['name']);
});

it('rejects applying to a project owned by another user', function () {
    $other = User::factory()->create();
    $foreign = Project::create(['user_id' => $other->id, 'name' => 'Foreign', 'rigor_level' => 2]);

    $response = ManagementServer::tool(ApplyManifest::class, [
        'manifest' => [
            'project' => ['id' => $foreign->id, 'name' => 'Foreign'],
        ],
    ]);

    $response->assertHasErrors();
    expect(Project::withoutGlobalScopes()->find($foreign->id)->name)->toBe('Foreign');
});

it('applies architecture viewpoints, views and nested elements from scratch', function () {
    $response = ManagementServer::tool(ApplyManifest::class, [
        'manifest' => [
            'project' => ['name' => 'Arch', 'rigor_level' => 2, 'status' => 'active'],
            'concerns' => [
                ['slug' => 'persistence', 'text' => 'Data must survive reloads.'],
            ],
            'architecture' => [
                'viewpoints' => [
                    [
                        'slug' => 'custom-logical',
                        'name' => 'Custom Logical',
                        'concerns' => ['scalability'],
                        'element_types' => ['component', 'connector'],
                        'languages' => ['mermaid'],
                    ],
                ],
                'views' => [
                    [
                        'slug' => 'top-level',
                        'viewpoint' => 'custom-logical',
                        'name' => 'Top-level',
                        'addresses_concerns' => ['persistence'],
                        'elements' => [
                            ['slug' => 'store', 'kind' => 'entity', 'name' => 'TodoStore'],
                            ['slug' => 'api', 'kind' => 'entity', 'name' => 'TodoApi'],
                        ],
                    ],
                ],
            ],
        ],
    ]);

    $response->assertOk()->assertStructuredContent(function ($json) {
        $json->where('counts.viewpoints_created', 1)
            ->where('counts.views_created', 1)
            ->where('counts.elements_created', 2)
            ->has('slugs.viewpoints.custom-logical')
            ->has('slugs.views.top-level')
            ->has('slugs.elements.store')
            ->etc();
    });

    $project = Project::where('name', 'Arch')->first();
    expect(CustomViewpoint::where('project_id', $project->id)->where('name', 'Custom Logical')->exists())->toBeTrue();
    $view = DesignView::where('project_id', $project->id)->where('name', 'Top-level')->first();
    expect($view->viewpoint)->toBe('Custom Logical');
    expect($view->concerns()->count())->toBe(1);
    expect(DesignElement::where('design_view_id', $view->id)->count())->toBe(2);
});

it('accepts built-in viewpoint names on views without a custom viewpoint declaration', function () {
    $response = ManagementServer::tool(ApplyManifest::class, [
        'manifest' => [
            'project' => ['name' => 'BuiltIn', 'rigor_level' => 2, 'status' => 'active'],
            'architecture' => [
                'views' => [
                    ['slug' => 'lv', 'viewpoint' => 'logical', 'name' => 'Logical View'],
                ],
            ],
        ],
    ]);

    $response->assertOk()->assertStructuredContent(function ($json) {
        $json->where('counts.views_created', 1)
            ->where('counts.viewpoints_created', 0)
            ->etc();
    });

    expect(DesignView::where('name', 'Logical View')->first()->viewpoint)->toBe('logical');
});

it('rejects a view referencing an unknown viewpoint', function () {
    $response = ManagementServer::tool(ApplyManifest::class, [
        'manifest' => [
            'project' => ['name' => 'Unknown', 'rigor_level' => 2, 'status' => 'active'],
            'architecture' => [
                'views' => [
                    ['viewpoint' => 'made-up', 'name' => 'Bad View'],
                ],
            ],
        ],
    ]);

    $response->assertHasErrors(['unknown viewpoint']);
});

it('rejects a custom viewpoint whose name collides with a built-in', function () {
    $response = ManagementServer::tool(ApplyManifest::class, [
        'manifest' => [
            'project' => ['name' => 'Collide', 'rigor_level' => 2, 'status' => 'active'],
            'architecture' => [
                'viewpoints' => [
                    [
                        'name' => 'logical',
                        'concerns' => ['c'],
                        'element_types' => ['e'],
                        'languages' => ['l'],
                    ],
                ],
            ],
        ],
    ]);

    $response->assertHasErrors(['built-in viewpoint']);
});

it('replace mode wipes architecture entities as well', function () {
    $project = Project::create(['user_id' => $this->user->id, 'name' => 'Wipe Arch', 'rigor_level' => 2]);
    $viewpoint = CustomViewpoint::create([
        'project_id' => $project->id,
        'name' => 'Old VP',
        'concerns' => ['x'],
        'element_types' => ['e'],
        'languages' => ['l'],
    ]);
    $view = DesignView::create(['project_id' => $project->id, 'viewpoint' => 'Old VP', 'name' => 'Old View']);
    DesignElement::create(['design_view_id' => $view->id, 'kind' => 'entity', 'name' => 'OldEl']);

    $response = ManagementServer::tool(ApplyManifest::class, [
        'manifest' => [
            'project' => ['id' => $project->id, 'name' => 'Wipe Arch'],
            'architecture' => [
                'views' => [
                    ['viewpoint' => 'logical', 'name' => 'Fresh View'],
                ],
            ],
        ],
        'mode' => 'replace',
        'confirm' => 'Wipe Arch',
    ]);

    $response->assertOk()->assertStructuredContent(function ($json) {
        $json->where('counts.viewpoints_deleted', 1)
            ->where('counts.views_deleted', 1)
            ->where('counts.elements_deleted', 1)
            ->where('counts.views_created', 1)
            ->etc();
    });

    expect(CustomViewpoint::where('project_id', $project->id)->where('name', 'Old VP')->exists())->toBeFalse();
    expect(DesignView::where('project_id', $project->id)->where('name', 'Old View')->exists())->toBeFalse();
    expect(DesignElement::where('name', 'OldEl')->exists())->toBeFalse();
    expect(DesignView::where('project_id', $project->id)->where('name', 'Fresh View')->exists())->toBeTrue();
});

it('merge mode updates an existing view in place by name', function () {
    $project = Project::create(['user_id' => $this->user->id, 'name' => 'Merge Arch', 'rigor_level' => 2]);
    DesignView::create(['project_id' => $project->id, 'viewpoint' => 'logical', 'name' => 'Top', 'description' => 'old']);

    $response = ManagementServer::tool(ApplyManifest::class, [
        'manifest' => [
            'project' => ['id' => $project->id, 'name' => 'Merge Arch'],
            'architecture' => [
                'views' => [
                    ['viewpoint' => 'logical', 'name' => 'Top', 'description' => 'new'],
                ],
            ],
        ],
        'mode' => 'merge',
    ]);

    $response->assertOk()->assertStructuredContent(function ($json) {
        $json->where('counts.views_updated', 1)
            ->where('counts.views_created', 0)
            ->etc();
    });

    expect(DesignView::where('project_id', $project->id)->where('name', 'Top')->first()->description)->toBe('new');
});

it('fail mode is a no-op when re-applying an identical viewpoint with array fields', function () {
    $manifest = [
        'project' => ['name' => 'Idempotent', 'rigor_level' => 2, 'status' => 'active'],
        'architecture' => [
            'viewpoints' => [
                [
                    'name' => 'Custom Logical',
                    'concerns' => ['scalability', 'security'],
                    'element_types' => ['component', 'connector'],
                    'languages' => ['mermaid'],
                ],
            ],
        ],
    ];

    ManagementServer::tool(ApplyManifest::class, ['manifest' => $manifest])->assertOk();

    $response = ManagementServer::tool(ApplyManifest::class, [
        'manifest' => array_merge($manifest, [
            'project' => ['id' => Project::where('name', 'Idempotent')->value('id'), 'name' => 'Idempotent', 'rigor_level' => 2, 'status' => 'active'],
        ]),
    ]);

    $response->assertOk()->assertStructuredContent(function ($json) {
        $json->where('counts.viewpoints_created', 0)
            ->where('counts.viewpoints_updated', 0)
            ->etc();
    });
});

it('reports drift on an architecture view when current updated_at is newer than _exported_at', function () {
    $project = Project::create(['user_id' => $this->user->id, 'name' => 'Drift Arch', 'rigor_level' => 2]);
    DesignView::create(['project_id' => $project->id, 'viewpoint' => 'logical', 'name' => 'Top']);

    $response = ManagementServer::tool(ApplyManifest::class, [
        'manifest' => [
            'project' => ['id' => $project->id, 'name' => 'Drift Arch'],
            'architecture' => [
                'views' => [
                    ['viewpoint' => 'logical', 'name' => 'Top', '_exported_at' => '2020-01-01T00:00:00Z'],
                ],
            ],
        ],
        'mode' => 'merge',
    ]);

    $response->assertOk()->assertStructuredContent(function ($json) {
        $json->has('drift.0')
            ->where('drift.0.entity', 'view')
            ->where('drift.0.identifier', 'Top')
            ->etc();
    });
});

it('creates project plan, roles, milestones and work items from scratch', function () {
    $response = ManagementServer::tool(ApplyManifest::class, [
        'manifest' => [
            'project' => ['name' => 'Plan', 'rigor_level' => 2, 'status' => 'active'],
            'capabilities' => [
                ['slug' => 'add-todo', 'text' => 'Adds a todo.', 'type' => 'functional'],
            ],
            'plan' => [
                'status' => 'draft',
                'scope_summary' => 'A small todo app.',
                'roles' => [
                    ['slug' => 'frontend', 'name' => 'Frontend'],
                    ['slug' => 'backend', 'name' => 'Backend'],
                ],
                'milestones' => [
                    ['slug' => 'm1', 'name' => 'MVP', 'target_date' => '2026-06-01'],
                ],
                'work_items' => [
                    [
                        'slug' => 'wi-add',
                        'name' => 'Implement add-todo',
                        'kind' => 'task',
                        'responsible_role' => 'frontend',
                        'capabilities' => ['add-todo'],
                        'milestones' => ['m1'],
                    ],
                ],
            ],
        ],
    ]);

    $response->assertOk()->assertStructuredContent(function ($json) {
        $json->where('counts.plan_created', true)
            ->where('counts.roles_created', 2)
            ->where('counts.milestones_created', 1)
            ->where('counts.work_items_created', 1)
            ->has('slugs.roles.frontend')
            ->has('slugs.milestones.m1')
            ->has('slugs.work_items.wi-add')
            ->etc();
    });

    $project = Project::where('name', 'Plan')->first();
    $plan = ProjectPlan::where('project_id', $project->id)->first();
    expect($plan->status)->toBe('draft');
    expect(Role::where('project_id', $project->id)->count())->toBe(2);
    $milestone = Milestone::where('project_id', $project->id)->where('name', 'MVP')->first();
    expect($milestone->target_date->toDateString())->toBe('2026-06-01');
    $workItem = WorkItem::where('project_id', $project->id)->where('name', 'Implement add-todo')->first();
    expect($workItem->responsibleRole->name)->toBe('Frontend');
    expect($workItem->requirements()->count())->toBe(1);
    expect($workItem->milestones()->count())->toBe(1);
});

it('resolves work-item parent and dependencies declared in the same manifest', function () {
    $response = ManagementServer::tool(ApplyManifest::class, [
        'manifest' => [
            'project' => ['name' => 'WiTree', 'rigor_level' => 2, 'status' => 'active'],
            'plan' => [
                'work_items' => [
                    ['slug' => 'parent', 'name' => 'Parent feature', 'kind' => 'deliverable'],
                    ['slug' => 'child', 'name' => 'Sub task', 'kind' => 'task', 'parent' => 'parent'],
                    [
                        'slug' => 'dependent',
                        'name' => 'Follows after',
                        'kind' => 'task',
                        'dependencies' => [
                            ['work_item' => 'child', 'kind' => 'finish_to_start'],
                        ],
                    ],
                ],
            ],
        ],
    ]);

    $response->assertOk();
    $project = Project::where('name', 'WiTree')->first();
    $parent = WorkItem::where('project_id', $project->id)->where('name', 'Parent feature')->first();
    $child = WorkItem::where('project_id', $project->id)->where('name', 'Sub task')->first();
    $dependent = WorkItem::where('project_id', $project->id)->where('name', 'Follows after')->first();
    expect($child->parent_id)->toBe($parent->id);
    expect($dependent->dependencies()->pluck('depends_on_id')->all())->toBe([$child->id]);
});

it('merge mode updates the singleton project plan in place', function () {
    $project = Project::create(['user_id' => $this->user->id, 'name' => 'PlanMerge', 'rigor_level' => 2]);
    ProjectPlan::create(['project_id' => $project->id, 'status' => 'draft', 'approach' => 'old']);

    $response = ManagementServer::tool(ApplyManifest::class, [
        'manifest' => [
            'project' => ['id' => $project->id, 'name' => 'PlanMerge'],
            'plan' => ['approach' => 'new'],
        ],
        'mode' => 'merge',
    ]);

    $response->assertOk()->assertStructuredContent(function ($json) {
        $json->where('counts.plan_updated', true)
            ->where('counts.plan_created', false)
            ->etc();
    });

    expect(ProjectPlan::where('project_id', $project->id)->value('approach'))->toBe('new');
});

it('rejects a work item referencing an unknown role', function () {
    $response = ManagementServer::tool(ApplyManifest::class, [
        'manifest' => [
            'project' => ['name' => 'BadRole', 'rigor_level' => 2, 'status' => 'active'],
            'plan' => [
                'work_items' => [
                    ['name' => 'Orphan', 'kind' => 'task', 'responsible_role' => 'ghost'],
                ],
            ],
        ],
    ]);

    $response->assertHasErrors(['unknown role']);
});

it('replace mode wipes plan, roles, milestones and work items', function () {
    $project = Project::create(['user_id' => $this->user->id, 'name' => 'PlanWipe', 'rigor_level' => 2]);
    ProjectPlan::create(['project_id' => $project->id, 'status' => 'draft']);
    $role = Role::create(['project_id' => $project->id, 'name' => 'OldRole']);
    $milestone = Milestone::create(['project_id' => $project->id, 'name' => 'OldMilestone']);
    WorkItem::create(['project_id' => $project->id, 'name' => 'OldWi', 'kind' => 'task', 'responsible_role_id' => $role->id]);

    $response = ManagementServer::tool(ApplyManifest::class, [
        'manifest' => [
            'project' => ['id' => $project->id, 'name' => 'PlanWipe'],
            'plan' => ['status' => 'active'],
        ],
        'mode' => 'replace',
        'confirm' => 'PlanWipe',
    ]);

    $response->assertOk()->assertStructuredContent(function ($json) {
        $json->where('counts.plan_deleted', true)
            ->where('counts.roles_deleted', 1)
            ->where('counts.milestones_deleted', 1)
            ->where('counts.work_items_deleted', 1)
            ->where('counts.plan_created', true)
            ->etc();
    });

    expect(Role::where('project_id', $project->id)->where('name', 'OldRole')->exists())->toBeFalse();
    expect(Milestone::where('project_id', $project->id)->where('name', 'OldMilestone')->exists())->toBeFalse();
    expect(WorkItem::where('project_id', $project->id)->where('name', 'OldWi')->exists())->toBeFalse();
    expect(ProjectPlan::where('project_id', $project->id)->value('status'))->toBe('active');
});

it('fail mode aborts when a milestone target_date differs', function () {
    $project = Project::create(['user_id' => $this->user->id, 'name' => 'DateDiff', 'rigor_level' => 2]);
    Milestone::create(['project_id' => $project->id, 'name' => 'MVP', 'target_date' => '2026-06-01']);

    $response = ManagementServer::tool(ApplyManifest::class, [
        'manifest' => [
            'project' => ['id' => $project->id, 'name' => 'DateDiff'],
            'plan' => [
                'milestones' => [
                    ['name' => 'MVP', 'target_date' => '2026-07-15'],
                ],
            ],
        ],
    ]);

    $response->assertHasErrors(['fail mode aborts']);
    expect(Milestone::where('project_id', $project->id)->where('name', 'MVP')->value('target_date')->toDateString())
        ->toBe('2026-06-01');
});

it('resolves work-item refs by existing names when no manifest slug matches', function () {
    $project = Project::create(['user_id' => $this->user->id, 'name' => 'NameRef', 'rigor_level' => 2]);
    Role::create(['project_id' => $project->id, 'name' => 'Backend']);
    Milestone::create(['project_id' => $project->id, 'name' => 'Beta']);

    $response = ManagementServer::tool(ApplyManifest::class, [
        'manifest' => [
            'project' => ['id' => $project->id, 'name' => 'NameRef'],
            'plan' => [
                'work_items' => [
                    [
                        'name' => 'Deliver',
                        'kind' => 'task',
                        'responsible_role' => 'Backend',
                        'milestones' => ['Beta'],
                    ],
                ],
            ],
        ],
    ]);

    $response->assertOk();
    $wi = WorkItem::where('project_id', $project->id)->where('name', 'Deliver')->first();
    expect($wi->responsibleRole->name)->toBe('Backend');
    expect($wi->milestones()->pluck('name')->all())->toBe(['Beta']);
});

it('accepts the bare-string dependency form', function () {
    $response = ManagementServer::tool(ApplyManifest::class, [
        'manifest' => [
            'project' => ['name' => 'BareDep', 'rigor_level' => 2, 'status' => 'active'],
            'plan' => [
                'work_items' => [
                    ['slug' => 'a', 'name' => 'A', 'kind' => 'task'],
                    ['slug' => 'b', 'name' => 'B', 'kind' => 'task', 'dependencies' => ['a']],
                ],
            ],
        ],
    ]);

    $response->assertOk();
    $project = Project::where('name', 'BareDep')->first();
    $b = WorkItem::where('project_id', $project->id)->where('name', 'B')->first();
    expect($b->dependencies()->pluck('name')->all())->toBe(['A']);
    expect($b->dependencies()->first()->pivot->kind)->toBe('finish_to_start');
});

it('reports drift on a work item when updated_at is newer than _exported_at', function () {
    $project = Project::create(['user_id' => $this->user->id, 'name' => 'WiDrift', 'rigor_level' => 2]);
    WorkItem::create(['project_id' => $project->id, 'name' => 'Some Task', 'kind' => 'task']);

    $response = ManagementServer::tool(ApplyManifest::class, [
        'manifest' => [
            'project' => ['id' => $project->id, 'name' => 'WiDrift'],
            'plan' => [
                'work_items' => [
                    ['name' => 'Some Task', 'kind' => 'task', '_exported_at' => '2020-01-01T00:00:00Z'],
                ],
            ],
        ],
        'mode' => 'merge',
    ]);

    $response->assertOk()->assertStructuredContent(function ($json) {
        $json->has('drift.0')
            ->where('drift.0.entity', 'work_item')
            ->where('drift.0.identifier', 'Some Task')
            ->etc();
    });
});

it('creates verification plans and nested cases linked to capabilities', function () {
    $response = ManagementServer::tool(ApplyManifest::class, [
        'manifest' => [
            'project' => ['name' => 'Verif', 'rigor_level' => 2, 'status' => 'active'],
            'capabilities' => [
                ['slug' => 'add-todo', 'text' => 'Adds a todo.', 'type' => 'functional'],
            ],
            'verification' => [
                'plans' => [
                    [
                        'slug' => 'unit',
                        'level' => 'unit',
                        'name' => 'Unit Verification',
                        'cases' => [
                            [
                                'slug' => 'c-add',
                                'name' => 'add-todo creates a row',
                                'expected_results' => 'A new row exists in storage.',
                                'verifies_capabilities' => ['add-todo'],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ]);

    $response->assertOk()->assertStructuredContent(function ($json) {
        $json->where('counts.verification_plans_created', 1)
            ->where('counts.verification_cases_created', 1)
            ->has('slugs.verification_plans.unit')
            ->has('slugs.verification_cases.c-add')
            ->etc();
    });

    $project = Project::where('name', 'Verif')->first();
    $plan = TestPlan::where('project_id', $project->id)->where('name', 'Unit Verification')->first();
    expect($plan->level)->toBe('unit');
    $case = TestCase::where('test_plan_id', $plan->id)->where('name', 'add-todo creates a row')->first();
    expect($case->requirements()->count())->toBe(1);
    expect($case->requirements()->first()->slug)->toBe('add-todo');
});

it('resolves verification case capabilities by existing slug when not in manifest', function () {
    $project = Project::create(['user_id' => $this->user->id, 'name' => 'ExistingCaps', 'rigor_level' => 2]);
    $cap = Requirement::create([
        'project_id' => $project->id,
        'slug' => 'pre-existing',
        'doc' => 'srs',
        'type' => 'functional',
        'text' => 'Pre-existing capability.',
    ]);

    $response = ManagementServer::tool(ApplyManifest::class, [
        'manifest' => [
            'project' => ['id' => $project->id, 'name' => 'ExistingCaps'],
            'verification' => [
                'plans' => [
                    [
                        'level' => 'system',
                        'name' => 'System',
                        'cases' => [
                            [
                                'name' => 'links existing capability',
                                'expected_results' => 'ok',
                                'verifies_capabilities' => ['pre-existing'],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ]);

    $response->assertOk();
    $case = TestCase::where('name', 'links existing capability')->first();
    expect($case->requirements()->pluck('id')->all())->toBe([$cap->id]);
});

it('merge mode updates an existing verification plan and case in place', function () {
    $project = Project::create(['user_id' => $this->user->id, 'name' => 'VerifMerge', 'rigor_level' => 2]);
    $plan = TestPlan::create([
        'project_id' => $project->id,
        'level' => 'unit',
        'name' => 'UnitV',
        'scope' => 'old scope',
    ]);
    TestCase::create([
        'test_plan_id' => $plan->id,
        'name' => 'case-a',
        'expected_results' => 'old result',
    ]);

    $response = ManagementServer::tool(ApplyManifest::class, [
        'manifest' => [
            'project' => ['id' => $project->id, 'name' => 'VerifMerge'],
            'verification' => [
                'plans' => [
                    [
                        'level' => 'unit',
                        'name' => 'UnitV',
                        'scope' => 'new scope',
                        'cases' => [
                            ['name' => 'case-a', 'expected_results' => 'new result'],
                        ],
                    ],
                ],
            ],
        ],
        'mode' => 'merge',
    ]);

    $response->assertOk()->assertStructuredContent(function ($json) {
        $json->where('counts.verification_plans_updated', 1)
            ->where('counts.verification_cases_updated', 1)
            ->where('counts.verification_plans_created', 0)
            ->where('counts.verification_cases_created', 0)
            ->etc();
    });

    expect(TestPlan::find($plan->id)->scope)->toBe('new scope');
    expect(TestCase::where('test_plan_id', $plan->id)->where('name', 'case-a')->value('expected_results'))->toBe('new result');
});

it('replace mode wipes verification plans and cases', function () {
    $project = Project::create(['user_id' => $this->user->id, 'name' => 'VerifWipe', 'rigor_level' => 2]);
    $plan = TestPlan::create(['project_id' => $project->id, 'level' => 'unit', 'name' => 'OldPlan']);
    TestCase::create(['test_plan_id' => $plan->id, 'name' => 'old-case', 'expected_results' => 'x']);

    $response = ManagementServer::tool(ApplyManifest::class, [
        'manifest' => [
            'project' => ['id' => $project->id, 'name' => 'VerifWipe'],
            'verification' => [
                'plans' => [
                    ['level' => 'unit', 'name' => 'FreshPlan'],
                ],
            ],
        ],
        'mode' => 'replace',
        'confirm' => 'VerifWipe',
    ]);

    $response->assertOk()->assertStructuredContent(function ($json) {
        $json->where('counts.verification_plans_deleted', 1)
            ->where('counts.verification_cases_deleted', 1)
            ->where('counts.verification_plans_created', 1)
            ->etc();
    });

    expect(TestPlan::where('project_id', $project->id)->where('name', 'OldPlan')->exists())->toBeFalse();
    expect(TestCase::where('name', 'old-case')->exists())->toBeFalse();
    expect(TestPlan::where('project_id', $project->id)->where('name', 'FreshPlan')->exists())->toBeTrue();
});

it('rejects a verification case referencing an unknown capability', function () {
    $response = ManagementServer::tool(ApplyManifest::class, [
        'manifest' => [
            'project' => ['name' => 'BadCap', 'rigor_level' => 2, 'status' => 'active'],
            'verification' => [
                'plans' => [
                    [
                        'level' => 'unit',
                        'name' => 'U',
                        'cases' => [
                            [
                                'name' => 'orphan',
                                'expected_results' => 'never matches',
                                'verifies_capabilities' => ['ghost-cap'],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ]);

    $response->assertHasErrors(['unknown capability']);
});

it('rejects a verification case missing expected_results', function () {
    $response = ManagementServer::tool(ApplyManifest::class, [
        'manifest' => [
            'project' => ['name' => 'BadCase', 'rigor_level' => 2, 'status' => 'active'],
            'verification' => [
                'plans' => [
                    ['level' => 'unit', 'name' => 'U', 'cases' => [['name' => 'noresult']]],
                ],
            ],
        ],
    ]);

    $response->assertHasErrors(['expected_results']);
});

it('reports drift on a verification case when updated_at is newer than _exported_at', function () {
    $project = Project::create(['user_id' => $this->user->id, 'name' => 'VCaseDrift', 'rigor_level' => 2]);
    $plan = TestPlan::create(['project_id' => $project->id, 'level' => 'unit', 'name' => 'U']);
    TestCase::create(['test_plan_id' => $plan->id, 'name' => 'CaseX', 'expected_results' => 'now']);

    $response = ManagementServer::tool(ApplyManifest::class, [
        'manifest' => [
            'project' => ['id' => $project->id, 'name' => 'VCaseDrift'],
            'verification' => [
                'plans' => [
                    [
                        'level' => 'unit',
                        'name' => 'U',
                        'cases' => [
                            [
                                'name' => 'CaseX',
                                'expected_results' => 'now',
                                '_exported_at' => '2020-01-01T00:00:00Z',
                            ],
                        ],
                    ],
                ],
            ],
        ],
        'mode' => 'merge',
    ]);

    $response->assertOk()->assertStructuredContent(function ($json) {
        $json->has('drift.0')
            ->where('drift.0.entity', 'verification_case')
            ->where('drift.0.identifier', 'CaseX')
            ->etc();
    });
});
