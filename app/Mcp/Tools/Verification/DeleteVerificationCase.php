<?php

namespace App\Mcp\Tools\Verification;

use App\Models\TestCase as TestCaseModel;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Delete one verification case and its runs.')]
class DeleteVerificationCase extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate(['id' => 'required|string|owned_test_case']);
        $case = TestCaseModel::findOrFail($data['id']);
        $runs = $case->runs()->count();
        $capabilities = $case->requirements()->count();
        $case->delete();

        return Response::structured(['id' => $data['id'], 'deleted' => true, 'runs_deleted' => $runs, 'capabilities_unlinked' => $capabilities]);
    }

    public function schema(JsonSchema $schema): array
    {
        return ['id' => $schema->string()->description('Verification case ULID')->required()];
    }
}
