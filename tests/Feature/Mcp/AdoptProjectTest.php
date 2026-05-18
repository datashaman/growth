<?php

use App\Mcp\Servers\ManagementServer;
use App\Mcp\Tools\Projects\AdoptProject;
use App\Models\Project;
use App\Models\ProjectPlanBaseline;
use App\Models\User;
use Laravel\Passport\Passport;

beforeEach(function () {
    $this->user = User::factory()->create();
    Passport::actingAs($this->user, ['mcp:use']);
});

it('adopts a repo as a project with an adoption baseline at HEAD', function () {
    ManagementServer::tool(AdoptProject::class, [
        'github_repo' => 'datashaman/legacy-api',
        'name' => 'Legacy API',
    ])
        ->assertOk()
        ->assertStructuredContent(function ($json) {
            $json->where('adopted', true)
                ->where('github_repo', 'datashaman/legacy-api')
                ->where('name', 'Legacy API')
                ->where('rigor_level', 1)
                ->where('adoption_baseline_version', 1)
                ->etc();
        });

    $project = Project::where('github_repo', 'datashaman/legacy-api')->first();

    expect($project)->not->toBeNull()
        ->and($project->adopted_at)->not->toBeNull()
        ->and($project->rigor_level)->toBe(1);

    $baseline = $project->projectPlan->baselines()->sole();
    expect($baseline->kind)->toBe('adoption')
        ->and($baseline->version)->toBe(1);
});

it('is idempotent — re-adopting a bound repo creates nothing new', function () {
    $args = ['github_repo' => 'datashaman/legacy-api', 'name' => 'Legacy API'];

    ManagementServer::tool(AdoptProject::class, $args)->assertOk();
    $project = Project::where('github_repo', 'datashaman/legacy-api')->sole();
    $adoptedAt = $project->adopted_at;

    ManagementServer::tool(AdoptProject::class, ['github_repo' => 'datashaman/legacy-api', 'name' => 'Renamed'])
        ->assertOk()
        ->assertStructuredContent(function ($json) {
            $json->where('adopted', false)->etc();
        });

    expect(Project::where('github_repo', 'datashaman/legacy-api')->count())->toBe(1)
        ->and(ProjectPlanBaseline::count())->toBe(1)
        ->and($project->fresh()->adopted_at->equalTo($adoptedAt))->toBeTrue()
        ->and($project->fresh()->name)->toBe('Legacy API');
});

it('refuses a repo already bound to a project in another workspace', function () {
    $other = User::factory()->create();
    Project::create([
        'workspace_id' => $other->active_workspace_id,
        'name' => 'Foreign',
        'rigor_level' => 2,
        'github_repo' => 'datashaman/foreign',
    ]);

    ManagementServer::tool(AdoptProject::class, [
        'github_repo' => 'datashaman/foreign',
        'name' => 'Mine',
    ])->assertHasErrors();

    // Nothing was created in the acting user's workspace.
    expect(Project::where('github_repo', 'datashaman/foreign')->count())->toBe(0);
});

it('rejects a malformed repo argument', function () {
    ManagementServer::tool(AdoptProject::class, ['github_repo' => 'not-a-repo', 'name' => 'X'])
        ->assertHasErrors();

    expect(Project::count())->toBe(0);
});
