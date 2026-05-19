<?php

namespace App\Mcp\Tools\Projects;

use App\Models\Project;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\File;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[IsDestructive(false)]
#[Description('Produce a ready-to-commit growth-sync GitHub Actions workflow for a project, plus the remaining one-time setup steps, so a repository can be onboarded to Growth in a single call.')]
class ScaffoldGithubSync extends Tool
{
    /**
     * Path, relative to the workflow root, the scaffolded file belongs at.
     */
    private const WORKFLOW_PATH = '.github/workflows/growth-sync.yml';

    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'project_id' => ['required', 'string', 'owned_project'],
            'ci_workflows' => ['nullable', 'array'],
            'ci_workflows.*' => ['string', 'max:255', 'regex:/^[^\r\n]+$/'],
        ]);

        $project = Project::findOrFail($data['project_id']);

        $yaml = File::get(base_path('actions/growth-sync/workflow.example.yml'));

        $ciWorkflows = array_values(array_filter($data['ci_workflows'] ?? []));
        if ($ciWorkflows !== []) {
            $yaml = $this->applyCiWorkflows($yaml, $ciWorkflows);
        }

        $bound = $project->github_repo !== null && $project->github_repo !== '';

        return Response::structured([
            'project_id' => $project->id,
            'project_name' => $project->name,
            'github_repo' => $project->github_repo,
            'github_repo_bound' => $bound,
            'workflow_path' => self::WORKFLOW_PATH,
            'workflow_yaml' => $yaml,
            'setup_steps' => $this->setupSteps($project, $bound),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'project_id' => $schema->string()->description('Project ULID the repository should sync into')->required(),
            'ci_workflows' => $schema->array()
                ->description('Names of the GitHub Actions CI workflows to record as check evidence. When given, the workflow_run trigger list is filled in; otherwise it keeps a placeholder for the adopter to edit.')
                ->items($schema->string()),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'project_id' => $schema->string()->required(),
            'project_name' => $schema->string()->required(),
            'github_repo' => $schema->string(),
            'github_repo_bound' => $schema->boolean()->required(),
            'workflow_path' => $schema->string()->required(),
            'workflow_yaml' => $schema->string()->required(),
            'setup_steps' => $schema->array()
                ->description('One-time setup steps. "done" is true only where Growth can confirm it.')
                ->items($schema->object(fn (JsonSchema $s) => [
                    'id' => $s->string()->required(),
                    'label' => $s->string()->required(),
                    'description' => $s->string()->required(),
                    'done' => $s->boolean()->required(),
                ]))
                ->required(),
        ];
    }

    /**
     * Replace the placeholder workflow_run trigger list with the adopter's
     * real CI workflow names. The `workflows:` key appears once in the
     * template, under the workflow_run trigger.
     *
     * The names are JSON-encoded: the result is always a single-line, valid
     * YAML flow sequence, so a name containing a comma, bracket, hash, or
     * other YAML metacharacter cannot break or inject into the scaffolded
     * workflow. A callback replacement keeps any `$`/`\` in a name out of
     * preg's backreference syntax.
     *
     * The template's "# EDIT: list your CI workflow name(s)" comment block
     * is dropped — the list is now filled in, so the instruction is stale.
     *
     * @param  list<string>  $ciWorkflows
     */
    private function applyCiWorkflows(string $yaml, array $ciWorkflows): string
    {
        $list = json_encode($ciWorkflows, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $yaml = preg_replace('/^[ \t]*# EDIT:.*\n(?:[ \t]*#.*\n)*/m', '', $yaml, 1);

        return preg_replace_callback(
            '/^(\s*workflows:) \[.*\]$/m',
            fn (array $match): string => $match[1].' '.$list,
            $yaml,
            1,
        );
    }

    /**
     * The one-time steps that scaffolding cannot perform itself: writing the
     * file into the adopter repo, provisioning the GitHub secret/variable,
     * and binding the project to the repository.
     *
     * @return list<array{id: string, label: string, description: string, done: bool}>
     */
    private function setupSteps(Project $project, bool $bound): array
    {
        return [
            [
                'id' => 'workflow_file',
                'label' => 'Commit the workflow file',
                'description' => 'Write workflow_yaml to '.self::WORKFLOW_PATH.' in the adopter repository. If your CI runs on GitHub Actions and you did not pass ci_workflows, edit the workflow_run.workflows list to name your CI workflow(s).',
                'done' => false,
            ],
            [
                'id' => 'mcp_token',
                'label' => 'Add the GROWTH_MCP_TOKEN secret',
                'description' => 'Mint a Passport personal access token with the mcp:use scope and add it as the GROWTH_MCP_TOKEN repository secret.',
                'done' => false,
            ],
            [
                'id' => 'growth_url',
                'label' => 'Add the GROWTH_URL variable',
                'description' => 'Add a GROWTH_URL repository variable pointing at this Growth instance URL.',
                'done' => false,
            ],
            [
                'id' => 'repo_binding',
                'label' => 'Bind the project to the repository',
                'description' => $bound
                    ? 'The project is already bound to '.$project->github_repo.'.'
                    : 'Set the project github_repo field to the repository in owner/repo form with the update-project tool, so deployment and release events resolve to this project.',
                'done' => $bound,
            ],
        ];
    }
}
