<?php

use App\Growth\Manifest\ManifestExporter;
use App\Mcp\Servers\ManagementServer;
use App\Mcp\Tools\Manifest\ApplyManifest;
use App\Mcp\Tools\Manifest\ExportManifest;
use App\Models\Project;
use App\Models\Requirement;
use App\Models\User;
use Laravel\Passport\Passport;

beforeEach(function () {
    $this->user = User::factory()->create();
    Passport::actingAs($this->user, ['mcp:use']);
});

function fullManifest(): array
{
    return [
        'project' => [
            'name' => 'Roundtrip',
            'description' => 'Full coverage manifest.',
            'rigor_level' => 2,
            'status' => 'active',
        ],
        'stakeholders' => [
            ['slug' => 'pm', 'name' => 'Product Manager', 'role' => 'PM', 'kind' => 'individual'],
            ['slug' => 'sec', 'name' => 'Security Lead', 'role' => 'security', 'kind' => 'individual'],
        ],
        'concerns' => [
            ['slug' => 'perf', 'text' => 'Performance budgets must hold.', 'raised_by' => 'pm'],
            ['slug' => 'auth', 'text' => 'Authentication must be hardened.', 'raised_by' => 'sec'],
        ],
        'requirements' => [
            ['slug' => 'cap-a', 'text' => 'The app shall greet users.', 'type' => 'functional', 'priority' => 'high'],
            ['slug' => 'cap-b', 'text' => 'The app shall persist state.', 'type' => 'functional'],
        ],
        'architecture' => [
            'viewpoints' => [
                [
                    'slug' => 'custom-vp',
                    'name' => 'Custom Logical',
                    'concerns' => ['scalability'],
                    'element_types' => ['component'],
                    'languages' => ['mermaid'],
                ],
            ],
            'views' => [
                [
                    'slug' => 'top',
                    'name' => 'Top-level',
                    'viewpoint' => 'logical',
                    'addresses_concerns' => ['perf'],
                    'elements' => [
                        ['slug' => 'store', 'kind' => 'entity', 'name' => 'TodoStore'],
                    ],
                ],
                [
                    'slug' => 'custom-view',
                    'name' => 'Custom Top',
                    'viewpoint' => 'custom-vp',
                ],
            ],
        ],
        'plan' => [
            'status' => 'active',
            'scope_summary' => 'Single-page app.',
            'approach' => 'Local-first.',
            'roles' => [
                ['slug' => 'fe', 'name' => 'Frontend', 'responsibilities' => 'UI work.'],
            ],
            'milestones' => [
                ['slug' => 'm1', 'name' => 'MVP', 'status' => 'pending'],
            ],
            'work_items' => [
                [
                    'slug' => 'wi-1',
                    'name' => 'Implement greeting',
                    'kind' => 'deliverable',
                    'status' => 'todo',
                    'requirements' => ['cap-a'],
                    'milestones' => ['m1'],
                    'responsible_role' => 'fe',
                ],
                [
                    'slug' => 'wi-2',
                    'name' => 'Wire persistence',
                    'kind' => 'task',
                    'status' => 'todo',
                    'requirements' => ['cap-b'],
                    'dependencies' => ['wi-1'],
                ],
            ],
        ],
        'verification' => [
            'plans' => [
                [
                    'slug' => 'unit',
                    'name' => 'Unit',
                    'level' => 'unit',
                    'scope' => 'Unit-level checks.',
                    'cases' => [
                        [
                            'slug' => 'c-greet',
                            'name' => 'greeting renders',
                            'expected_results' => 'The greeting element is visible after first load.',
                            'verifies_requirements' => ['cap-a'],
                        ],
                    ],
                ],
            ],
        ],
    ];
}

function applyAndGetProjectId(array $manifest): string
{
    $response = ManagementServer::tool(ApplyManifest::class, ['manifest' => $manifest]);
    $response->assertOk();
    $captured = null;
    $response->assertStructuredContent(function ($json) use (&$captured) {
        $captured = $json->toArray();
        $json->etc();
    });

    return $captured['project_id'];
}

it('returns a bounded TOC by default', function () {
    $project = Project::create([
        'workspace_id' => $this->user->active_workspace_id,
        'name' => 'Empty',
        'description' => 'Nothing yet.',
        'rigor_level' => 2,
        'status' => 'active',
    ]);

    $response = ManagementServer::tool(ExportManifest::class, ['project_id' => $project->id]);

    $response->assertOk()->assertStructuredContent(function ($json) use ($project) {
        $json->where('mode', 'toc')
            ->where('toc.project.id', $project->id)
            ->where('toc.project.name', 'Empty')
            ->where('toc.sections.stakeholders.count', 0)
            ->where('toc.sections.concerns.count', 0)
            ->where('toc.sections.requirements.count', 0)
            ->where('toc.sections.plan.present', false)
            ->where('toc.resource_uris.toc', "growth://projects/{$project->id}/manifest")
            ->where('toc.resource_uris.sections.requirements', "growth://projects/{$project->id}/manifest/requirements")
            ->etc();
    });
});

it('exports the full manifest when sections is ["*"]', function () {
    $project = Project::create([
        'workspace_id' => $this->user->active_workspace_id,
        'name' => 'Empty',
        'description' => 'Nothing yet.',
        'rigor_level' => 2,
        'status' => 'active',
    ]);

    $response = ManagementServer::tool(ExportManifest::class, [
        'project_id' => $project->id,
        'sections' => ['*'],
    ]);

    $response->assertOk()->assertStructuredContent(function ($json) use ($project) {
        $json->where('mode', 'manifest')
            ->where('manifest.project.id', $project->id)
            ->where('manifest.project.name', 'Empty')
            ->where('manifest.project.rigor_level', 2)
            ->where('manifest.project.status', 'active')
            ->has('manifest.project._exported_at')
            ->etc();
    });
});

it('exports only the requested sections', function () {
    $projectId = applyAndGetProjectId(fullManifest());

    $response = ManagementServer::tool(ExportManifest::class, [
        'project_id' => $projectId,
        'sections' => ['stakeholders', 'requirements'],
    ]);

    $response->assertOk()->assertStructuredContent(function ($json) {
        $json->where('mode', 'manifest')
            ->has('manifest.project')
            ->has('manifest.stakeholders')
            ->has('manifest.requirements')
            ->missing('manifest.concerns')
            ->missing('manifest.architecture')
            ->missing('manifest.plan')
            ->missing('manifest.verification')
            ->etc();
    });
});

it('keeps the TOC response an order of magnitude smaller than the full manifest', function () {
    // This is the overflow case from #337: on a populated project the full
    // manifest can blow the MCP token budget; the TOC must be a bounded fraction.
    $projectId = applyAndGetProjectId(fullManifest());

    $full = app(ManifestExporter::class)->export($projectId);
    $toc = app(ManifestExporter::class)->tableOfContents($projectId);

    $fullSize = strlen(json_encode($full));
    $tocSize = strlen(json_encode($toc));

    expect($tocSize)->toBeLessThan($fullSize / 2)
        ->and($fullSize)->toBeGreaterThan(2000);
});

it('rejects unknown section names', function () {
    $project = Project::create([
        'workspace_id' => $this->user->active_workspace_id,
        'name' => 'Empty',
        'rigor_level' => 2,
        'status' => 'active',
    ]);

    $response = ManagementServer::tool(ExportManifest::class, [
        'project_id' => $project->id,
        'sections' => ['bogus'],
    ]);

    $response->assertHasErrors();
});

it('rejects a foreign project_id', function () {
    $foreign = User::factory()->create();
    $project = Project::withoutGlobalScopes()->create([
        'workspace_id' => $foreign->active_workspace_id,
        'name' => 'Foreign',
        'rigor_level' => 2,
    ]);

    $response = ManagementServer::tool(ExportManifest::class, ['project_id' => $project->id]);

    $response->assertHasErrors();
});

it('round-trips a fully populated project through apply→export→re-apply with all zero counts', function () {
    $projectId = applyAndGetProjectId(fullManifest());

    $exported = app(ManifestExporter::class)->export($projectId);

    $reapply = ManagementServer::tool(ApplyManifest::class, [
        'manifest' => $exported,
        'mode' => 'merge',
    ]);

    $reapply->assertOk()->assertStructuredContent(function ($json) {
        $json->where('counts.project_created', false)
            ->where('counts.project_updated', false)
            ->where('counts.stakeholders_created', 0)
            ->where('counts.stakeholders_updated', 0)
            ->where('counts.concerns_created', 0)
            ->where('counts.concerns_updated', 0)
            ->where('counts.requirements_created', 0)
            ->where('counts.requirements_updated', 0)
            ->where('counts.viewpoints_created', 0)
            ->where('counts.viewpoints_updated', 0)
            ->where('counts.views_created', 0)
            ->where('counts.views_updated', 0)
            ->where('counts.elements_created', 0)
            ->where('counts.elements_updated', 0)
            ->where('counts.plan_created', false)
            ->where('counts.plan_updated', false)
            ->where('counts.roles_created', 0)
            ->where('counts.roles_updated', 0)
            ->where('counts.milestones_created', 0)
            ->where('counts.milestones_updated', 0)
            ->where('counts.work_items_created', 0)
            ->where('counts.work_items_updated', 0)
            ->where('counts.verification_plans_created', 0)
            ->where('counts.verification_plans_updated', 0)
            ->where('counts.verification_cases_created', 0)
            ->where('counts.verification_cases_updated', 0)
            ->where('drift', [])
            ->etc();
    });
});

it('round-trips the renders_ui flag through apply and export', function () {
    $manifest = fullManifest();
    $manifest['requirements'][0]['renders_ui'] = true; // cap-a is UI-bearing

    $projectId = applyAndGetProjectId($manifest);

    // apply read the flag — cap-a is UI-bearing, cap-b defaults to false
    $capA = Requirement::where('project_id', $projectId)->where('slug', 'cap-a')->sole();
    $capB = Requirement::where('project_id', $projectId)->where('slug', 'cap-b')->sole();
    expect($capA->renders_ui)->toBeTrue()
        ->and($capB->renders_ui)->toBeFalse();

    // export emits the flag, and only for the UI-bearing requirement
    $exported = app(ManifestExporter::class)->export($projectId);
    $exportedA = collect($exported['requirements'])->firstWhere('slug', 'cap-a');
    $exportedB = collect($exported['requirements'])->firstWhere('slug', 'cap-b');
    expect($exportedA['renders_ui'])->toBeTrue()
        ->and($exportedB)->not->toHaveKey('renders_ui');
});

it('produces byte-identical output across two consecutive exports', function () {
    $projectId = applyAndGetProjectId(fullManifest());

    $exporter = app(ManifestExporter::class);
    $first = $exporter->export($projectId);
    $second = $exporter->export($projectId);

    expect(json_encode($first))->toBe(json_encode($second));
});

it('emits stakeholders and concerns sorted by slug', function () {
    $projectId = applyAndGetProjectId(fullManifest());

    $manifest = app(ManifestExporter::class)->export($projectId);

    // Exporter derives slugs from names/text, ignoring input slugs.
    expect(array_column($manifest['stakeholders'], 'slug'))->toBe([
        'product-manager',
        'security-lead',
    ]);
    expect(array_column($manifest['concerns'], 'slug'))->toBe([
        'authentication-must-be-hardened',
        'performance-budgets-must-hold',
    ]);
});

it('emits views referencing built-in viewpoints by name and does not synthesize a custom viewpoint for them', function () {
    $projectId = applyAndGetProjectId(fullManifest());

    $manifest = app(ManifestExporter::class)->export($projectId);

    expect(array_column($manifest['architecture']['viewpoints'], 'name'))->toBe(['Custom Logical']);
    $topView = collect($manifest['architecture']['views'])->firstWhere('name', 'Top-level');
    expect($topView['viewpoint'])->toBe('logical');
});

it('round-trip resolves raised_by via exporter-derived stakeholder slugs', function () {
    // Input manifest has no stakeholder slugs. Concerns reference stakeholders
    // by name (which the applier accepts). After export, raised_by emits the
    // exporter-derived slug; re-apply must resolve via that slug.
    $manifest = fullManifest();
    foreach ($manifest['stakeholders'] as &$row) {
        unset($row['slug']);
    }
    unset($row);
    foreach ($manifest['concerns'] as &$c) {
        if (($c['raised_by'] ?? null) === 'pm') {
            $c['raised_by'] = 'Product Manager';
        }
        if (($c['raised_by'] ?? null) === 'sec') {
            $c['raised_by'] = 'Security Lead';
        }
    }
    unset($c);

    $projectId = applyAndGetProjectId($manifest);
    $exported = app(ManifestExporter::class)->export($projectId);

    expect(array_column($exported['stakeholders'], 'slug'))
        ->toBe(['product-manager', 'security-lead']);
    $authConcern = collect($exported['concerns'])->firstWhere('text', 'Authentication must be hardened.');
    expect($authConcern['raised_by'])->toBe('security-lead');

    $reapply = ManagementServer::tool(ApplyManifest::class, [
        'manifest' => $exported,
        'mode' => 'merge',
    ]);

    $reapply->assertOk()->assertStructuredContent(function ($json) {
        $json->where('counts.stakeholders_updated', 0)
            ->where('counts.concerns_updated', 0)
            ->etc();
    });
});
