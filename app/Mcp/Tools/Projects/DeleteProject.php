<?php

namespace App\Mcp\Tools\Projects;

use App\Growth\Confirmation\ConfirmationGateway;
use App\Mcp\McpConfirmationGateway;
use App\Models\Project;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Elicitation\Elicitation;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[IsDestructive]
#[Description('Delete a project AND every artifact below it — stakeholders, concerns, requirements, design views/elements, custom viewpoints, test plans/cases/runs, anomalies. Destructive and irreversible. Pass a `confirm_name` matching the project name to delete it; when `confirm_name` is omitted and the client supports MCP elicitation, the caller is prompted to confirm interactively.')]
class DeleteProject extends Tool
{
    public function handle(Request $request, Elicitation $elicitation): ResponseFactory
    {
        $data = $request->validate([
            'id' => 'required|string|owned_project',
            'confirm_name' => 'nullable|string',
        ]);

        $project = Project::withCount([
            'stakeholders',
            'concerns',
            'requirements',
            'designViews',
            'customViewpoints',
            'testPlans',
            'anomalies',
        ])->findOrFail($data['id']);

        $counts = [
            'stakeholders_deleted' => $project->stakeholders_count,
            'concerns_deleted' => $project->concerns_count,
            'requirements_deleted' => $project->requirements_count,
            'design_views_deleted' => $project->design_views_count,
            'custom_viewpoints_deleted' => $project->custom_viewpoints_count,
            'test_plans_deleted' => $project->test_plans_count,
            'anomalies_deleted' => $project->anomalies_count,
        ];

        $confirmName = $data['confirm_name'] ?? null;

        if ($confirmName !== null && trim($confirmName) !== '') {
            if ($confirmName !== $project->name) {
                return new ResponseFactory(Response::error(
                    "Confirmation mismatch — pass the project's exact name in `confirm_name` to delete it. Project is named [{$project->name}]."
                ));
            }
        } else {
            $confirmed = $this->confirmDeletion($project, $counts, new McpConfirmationGateway($elicitation));

            if ($confirmed === null) {
                return new ResponseFactory(Response::error(
                    "This client cannot prompt for confirmation. Pass the project's exact name in `confirm_name` to delete it. Project is named [{$project->name}]."
                ));
            }

            if ($confirmed === false) {
                return new ResponseFactory(Response::error('Deletion cancelled — the project was not deleted.'));
            }
        }

        $project->delete();

        return Response::structured(['id' => $data['id'], 'deleted' => true] + $counts);
    }

    /**
     * Ask the MCP client to confirm the cascade before anything is deleted.
     * Returns `null` when the client cannot elicit, so the caller falls back to
     * the `confirm_name` guard instead.
     *
     * @param  array<string, int>  $counts
     */
    private function confirmDeletion(Project $project, array $counts, ConfirmationGateway $confirmation): ?bool
    {
        $artifacts = array_sum($counts);

        $message = "Permanently delete project [{$project->name}] and its {$artifacts} dependent "
            .($artifacts === 1 ? 'artifact' : 'artifacts')
            .' (stakeholders, concerns, requirements, design views/elements, custom viewpoints, '
            .'test plans/cases/runs, anomalies)? This cannot be undone.';

        return $confirmation->confirm($message);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()
                ->description('Project ULID to delete')
                ->required(),
            'confirm_name' => $schema->string()
                ->description('The project\'s exact name. Guards against accidental deletion. When omitted, the caller is prompted to confirm via MCP elicitation if the client supports it.'),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->required(),
            'deleted' => $schema->boolean()->required(),
            'stakeholders_deleted' => $schema->integer()->required(),
            'concerns_deleted' => $schema->integer()->required(),
            'requirements_deleted' => $schema->integer()->required(),
            'design_views_deleted' => $schema->integer()->required(),
            'custom_viewpoints_deleted' => $schema->integer()->required(),
            'test_plans_deleted' => $schema->integer()->required(),
            'anomalies_deleted' => $schema->integer()->required(),
        ];
    }
}
