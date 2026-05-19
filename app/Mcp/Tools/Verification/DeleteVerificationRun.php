<?php

namespace App\Mcp\Tools\Verification;

use App\Models\TestRun;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[IsDestructive]
#[Description('Delete one verification run.')]
class DeleteVerificationRun extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate(['id' => 'required|string|owned_test_run']);
        $run = TestRun::findOrFail($data['id']);
        $anomalies = $run->anomalies()->count();
        $run->delete();

        return Response::structured(['id' => $data['id'], 'deleted' => true, 'anomalies_detached' => $anomalies]);
    }

    public function schema(JsonSchema $schema): array
    {
        return ['id' => $schema->string()->description('Verification run ULID')->required()];
    }
}
