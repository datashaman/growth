<?php

namespace App\Mcp\Tools;

use App\Models\Project;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Delete a Growth project and its captured artifacts. Requires confirm_name to match the project name exactly.')]
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
                "Confirmation mismatch. Pass the project's exact name in `confirm_name` to delete it. Project is named [{$project->name}]."
            ));
        }

        $counts = [
            'stakeholders_deleted' => $project->stakeholders_count,
            'concerns_deleted' => $project->concerns_count,
            'capabilities_deleted' => $project->requirements_count,
            'architecture_views_deleted' => $project->design_views_count,
            'custom_viewpoints_deleted' => $project->custom_viewpoints_count,
            'verification_plans_deleted' => $project->test_plans_count,
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
                ->description('Must match the project name exactly to guard against accidental deletion')
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
            'capabilities_deleted' => $schema->integer()->required(),
            'architecture_views_deleted' => $schema->integer()->required(),
            'custom_viewpoints_deleted' => $schema->integer()->required(),
            'verification_plans_deleted' => $schema->integer()->required(),
            'anomalies_deleted' => $schema->integer()->required(),
        ];
    }
}
