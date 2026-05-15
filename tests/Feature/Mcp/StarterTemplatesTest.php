<?php

use App\Growth\Lint\BaselineLinter;
use App\Growth\Lint\ChangeLinter;
use App\Growth\Lint\DesignLinter;
use App\Growth\Lint\PmpLinter;
use App\Growth\Lint\RequirementLinter;
use App\Growth\Lint\ReviewLinter;
use App\Growth\Lint\TestLinter;
use App\Growth\Manifest\ManifestExporter;
use App\Mcp\Servers\ManagementServer;
use App\Mcp\Tools\Manifest\ApplyManifest;
use App\Models\Project;
use App\Models\User;
use Laravel\Passport\Passport;

beforeEach(function () {
    $this->user = User::factory()->create();
    Passport::actingAs($this->user, ['mcp:use']);
});

function readStarterTemplate(int $rigor): array
{
    $response = test()->postJson('/mcp/management', [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'resources/read',
        'params' => ['uri' => "growth://template/rigor-{$rigor}"],
    ])->assertOk()->json('result.contents');

    $body = collect($response)->firstWhere('uri', "growth://template/rigor-{$rigor}")['text'] ?? null;
    expect($body)->not->toBeNull();

    $decoded = json_decode($body, true);
    expect($decoded)->toBeArray();

    return $decoded;
}

it('registers all four starter templates on the management server', function () {
    $resources = $this->postJson('/mcp/management', [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'resources/list',
    ])->assertOk()->json('result.resources');

    $uris = collect($resources)->pluck('uri')->all();
    expect($uris)
        ->toContain('growth://template/rigor-1')
        ->toContain('growth://template/rigor-2')
        ->toContain('growth://template/rigor-3')
        ->toContain('growth://template/rigor-4');
});

it('serves each template as JSON with the matching rigor_level', function (int $rigor) {
    $manifest = readStarterTemplate($rigor);

    expect($manifest['project']['rigor_level'])->toBe($rigor);
    expect($manifest['project'])->toHaveKey('name');
    expect($manifest['plan'])->toHaveKey('scope_summary');
    expect($manifest['plan'])->toHaveKey('approach');
})->with([1, 2, 3, 4]);

it('Rigor 1 template applies cleanly via apply-manifest', function () {
    $manifest = readStarterTemplate(1);

    $response = ManagementServer::tool(ApplyManifest::class, ['manifest' => $manifest]);

    $response->assertOk()->assertStructuredContent(function ($json) {
        $json->where('counts.project_created', true)->etc();
    });
});

it('every template applies cleanly via apply-manifest', function (int $rigor) {
    $manifest = readStarterTemplate($rigor);

    $response = ManagementServer::tool(ApplyManifest::class, ['manifest' => $manifest]);

    $response->assertOk();
})->with([1, 2, 3, 4]);

it('applied templates produce no error-severity lint findings at their declared rigor', function (int $rigor) {
    $manifest = readStarterTemplate($rigor);

    $apply = ManagementServer::tool(ApplyManifest::class, ['manifest' => $manifest]);
    $apply->assertOk();
    $captured = null;
    $apply->assertStructuredContent(function ($json) use (&$captured) {
        $captured = $json->toArray();
        $json->etc();
    });
    $projectId = $captured['project_id'];

    $project = Project::find($projectId);

    $sections = [
        app(BaselineLinter::class)->check($project),
        app(ChangeLinter::class)->check($project),
        app(DesignLinter::class)->check($project),
        app(TestLinter::class)->check($project),
        app(PmpLinter::class)->check($project),
        app(ReviewLinter::class)->check($project),
    ];
    foreach ($project->requirements as $req) {
        $sections[] = app(RequirementLinter::class)->check($req);
    }

    $errors = collect($sections)->flatten(1)->where('severity', 'error')->values()->all();

    expect($errors)->toBe([], 'Template at rigor '.$rigor.' should produce no error-severity findings; got: '.json_encode($errors));
})->with([1, 2, 3, 4]);

it('every applied template round-trips through export with all-zero apply counts', function (int $rigor) {
    $manifest = readStarterTemplate($rigor);

    $apply = ManagementServer::tool(ApplyManifest::class, ['manifest' => $manifest]);
    $apply->assertOk();
    $captured = null;
    $apply->assertStructuredContent(function ($json) use (&$captured) {
        $captured = $json->toArray();
        $json->etc();
    });

    $exported = app(ManifestExporter::class)->export($captured['project_id']);

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
            ->etc();
    });
})->with([1, 2, 3, 4]);
