<?php

namespace App\Mcp\Tools;

use App\Mcp\Resources\ProjectDashboardApp;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\RendersApp;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Open the read-only Growth project dashboard app. Optionally provide a project_id to preselect a project.')]
#[IsReadOnly]
#[RendersApp(resource: ProjectDashboardApp::class)]
class ShowProjectDashboard extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'project_id' => 'nullable|string|owned_project',
        ]);

        return Response::structured([
            'message' => 'Project dashboard loaded.',
            'project_id' => $data['project_id'] ?? null,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'project_id' => $schema->string()
                ->description('Optional project ULID to preselect in the dashboard.'),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'message' => $schema->string()->required(),
            'project_id' => $schema->string(),
        ];
    }
}
