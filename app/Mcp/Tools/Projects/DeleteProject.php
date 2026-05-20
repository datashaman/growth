<?php

namespace App\Mcp\Tools\Projects;

use App\Models\Project;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[IsDestructive]
#[Description('Delete a project AND every artifact below it — stakeholders, concerns, requirements, design views/elements, custom viewpoints, test plans/cases/runs, anomalies. Destructive and irreversible. Requires a confirmation argument matching the project name to prevent accidents.')]
class DeleteProject extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'id' => 'required|string|owned_project',
            'confirm_name' => 'required|string',
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

        if ($data['confirm_name'] !== $project->name) {
            return new ResponseFactory(Response::error(
                "Confirmation mismatch — pass the project's exact name in `confirm_name` to delete it. Project is named [{$project->name}]."
            ));
        }

        $counts = [
            'stakeholders_deleted' => $project->stakeholders_count,
            'concerns_deleted' => $project->concerns_count,
            'requirements_deleted' => $project->requirements_count,
            'design_views_deleted' => $project->design_views_count,
            'custom_viewpoints_deleted' => $project->custom_viewpoints_count,
            'test_plans_deleted' => $project->test_plans_count,
            'anomalies_deleted' => $project->anomalies_count,
        ];

        $project->delete();

        return Response::structured(['id' => $data['id'], 'deleted' => true] + $counts);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()
                ->description('Project ULID to delete')
                ->required(),
            'confirm_name' => $schema->string()
                ->description('Must match the project\'s name exactly — guards against accidental deletion.')
                ->required(),
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
