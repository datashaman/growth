<?php

namespace App\Mcp\Tools\Dashboard;

use App\Mcp\Resources\CapabilityExplorerApp;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\RendersApp;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Open the Growth capability-explorer app: browse capabilities with layer/type/priority filters, drill into acceptance checks, derived design/test/work-item links, and capability lint findings. Optionally provide a project_id to preselect a project.')]
#[IsReadOnly]
#[RendersApp(resource: CapabilityExplorerApp::class)]
class ShowCapabilityExplorer extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'project_id' => 'nullable|string|owned_project',
        ]);

        return Response::structured([
            'message' => 'Capability explorer app loaded.',
            'project_id' => $data['project_id'] ?? null,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'project_id' => $schema->string()
                ->description('Optional project ULID to preselect in the capability-explorer app.'),
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
