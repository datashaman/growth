<?php

namespace App\Mcp\Tools\Test;

use App\Models\TestRun;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Delete a single test run. Anomalies that pointed at it have their test_run_id set to null but are not themselves deleted.')]
class DeleteTestRun extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'id' => 'required|string|owned_test_run',
        ]);

        $run = TestRun::findOrFail($data['id']);
        $orphanedAnomalies = $run->anomalies()->count();
        $run->delete();

        return Response::structured([
            'id' => $data['id'],
            'deleted' => true,
            'anomalies_orphaned' => $orphanedAnomalies,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()
                ->description('Test run ULID to delete')
                ->required(),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->required(),
            'deleted' => $schema->boolean()->required(),
            'anomalies_orphaned' => $schema->integer()->required(),
        ];
    }
}
