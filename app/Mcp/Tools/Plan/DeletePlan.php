<?php

namespace App\Mcp\Tools\Plan;

use App\Models\ProjectPlan;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[IsDestructive]
#[Description('Delete the master Project Management Plan for a project. Milestones, work items, and roles are NOT affected — they live independently.')]
class DeletePlan extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'id' => 'required|string|owned_project_plan',
        ]);

        ProjectPlan::findOrFail($data['id'])->delete();

        return Response::structured([
            'id' => $data['id'],
            'deleted' => true,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()
                ->description('ProjectPlan ULID')
                ->required(),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->required(),
            'deleted' => $schema->boolean()->required(),
        ];
    }
}
