<?php

namespace App\Mcp\Tools\Dashboard;

use App\Mcp\Resources\TraceGraphApp;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\RendersApp;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Open the Growth trace-graph app: pick a starting artifact (requirement from the sidebar or any artifact ULID) and visualize the trace-query nodes and edges as an interactive force-directed graph with adjustable depth and direction. Optionally provide a project_id to preselect a project.')]
#[IsReadOnly]
#[RendersApp(resource: TraceGraphApp::class)]
class ShowTraceGraph extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'project_id' => 'nullable|string|owned_project',
        ]);

        return Response::structured([
            'message' => 'Trace graph app loaded.',
            'project_id' => $data['project_id'] ?? null,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'project_id' => $schema->string()
                ->description('Optional project ULID to preselect in the trace-graph app.'),
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
