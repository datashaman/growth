<?php

use App\Mcp\Servers\ManagementServer;
use App\Mcp\Tools\Projects\ScaffoldGithubSync;
use App\Models\Project;
use App\Models\User;
use Laravel\Passport\Passport;

beforeEach(function () {
    $this->user = User::factory()->create();
    Passport::actingAs($this->user, ['mcp:use']);

    $this->project = Project::create([
        'workspace_id' => $this->user->active_workspace_id,
        'name' => 'Growth',
        'rigor_level' => 2,
        'github_repo' => 'datashaman/growth',
    ]);
});

it('scaffolds a current workflow for a repo-bound project', function () {
    ManagementServer::tool(ScaffoldGithubSync::class, [
        'project_id' => $this->project->id,
    ])
        ->assertOk()
        ->assertStructuredContent(function ($json) {
            $json->where('project_id', $this->project->id)
                ->where('project_name', 'Growth')
                ->where('github_repo', 'datashaman/growth')
                ->where('github_repo_bound', true)
                ->where('workflow_path', '.github/workflows/growth-sync.yml')
                // The template is the single source of truth: the scaffolded
                // YAML must carry the checks:write permission block.
                ->where('workflow_yaml', fn (string $yaml) => str_contains($yaml, 'checks: write')
                    && str_contains($yaml, 'datashaman/growth/actions/growth-sync@main')
                    // The template warns that the action's source repo must be
                    // reachable from the adopter repo.
                    && str_contains($yaml, 'Actions can only resolve it'))
                ->has('setup_steps', 5)
                ->where('setup_steps.1.id', 'action_access')
                ->where('setup_steps.2.id', 'mcp_token')
                ->where('setup_steps.2.description', fn (string $description) => str_contains($description, 'php artisan growth-sync:install '.$this->project->id.' <sync-user-email> --growth-url=<growth-url>')
                    && str_contains($description, 'writes it directly to the repository as GROWTH_MCP_TOKEN')
                    && str_contains($description, 'Do not generate or return this token through MCP'))
                ->where('setup_steps.4.id', 'repo_binding')
                ->where('setup_steps.4.done', true);
        });
});

it('reports the repo binding as incomplete when the project is unbound', function () {
    $unbound = Project::create([
        'workspace_id' => $this->user->active_workspace_id,
        'name' => 'Unbound',
        'rigor_level' => 2,
    ]);

    ManagementServer::tool(ScaffoldGithubSync::class, [
        'project_id' => $unbound->id,
    ])
        ->assertOk()
        ->assertStructuredContent(function ($json) {
            $json->where('github_repo', null)
                ->where('github_repo_bound', false)
                ->where('setup_steps.4.id', 'repo_binding')
                ->where('setup_steps.4.done', false)
                ->etc();
        });
});

it('fills in the workflow_run trigger list from ci_workflows', function () {
    ManagementServer::tool(ScaffoldGithubSync::class, [
        'project_id' => $this->project->id,
        'ci_workflows' => ['tests', 'linter'],
    ])
        ->assertOk()
        ->assertStructuredContent(function ($json) {
            $json->where('workflow_yaml', fn (string $yaml) => str_contains($yaml, 'workflows: ["tests","linter"]')
                // The list is filled in, so the "EDIT" instruction is dropped.
                && ! str_contains($yaml, '# EDIT:'))
                ->etc();
        });
});

it('safely quotes a CI workflow name containing YAML metacharacters', function () {
    ManagementServer::tool(ScaffoldGithubSync::class, [
        'project_id' => $this->project->id,
        'ci_workflows' => ['Build, Test', 'lint'],
    ])
        ->assertOk()
        ->assertStructuredContent(function ($json) {
            // The comma in the name must stay inside quotes — otherwise it
            // would split into two trigger entries or break the YAML.
            $json->where('workflow_yaml', fn (string $yaml) => str_contains($yaml, 'workflows: ["Build, Test","lint"]'))
                ->etc();
        });
});

it('rejects a CI workflow name spanning multiple lines', function () {
    ManagementServer::tool(ScaffoldGithubSync::class, [
        'project_id' => $this->project->id,
        'ci_workflows' => ["tests\ninjected: true"],
    ])->assertHasErrors();
});

it('leaves the placeholder trigger list when no ci_workflows are given', function () {
    ManagementServer::tool(ScaffoldGithubSync::class, [
        'project_id' => $this->project->id,
    ])
        ->assertOk()
        ->assertStructuredContent(function ($json) {
            $json->where('workflow_yaml', fn (string $yaml) => str_contains($yaml, 'workflows: [CI]')
                // No ci_workflows given, so the "EDIT" instruction stays.
                && str_contains($yaml, '# EDIT:'))
                ->etc();
        });
});

it('rejects a project in another workspace', function () {
    $other = User::factory()->create();
    $foreign = Project::create([
        'workspace_id' => $other->active_workspace_id,
        'name' => 'Foreign',
        'rigor_level' => 2,
    ]);

    ManagementServer::tool(ScaffoldGithubSync::class, [
        'project_id' => $foreign->id,
    ])->assertHasErrors();
});
