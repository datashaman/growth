<?php

namespace App\Mcp\Tools\Test;

use App\Models\TestCase as TestCaseModel;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Delete a single test case. Its runs cascade-delete; requirement traces are removed via the pivot.')]
class DeleteTestCase extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'id' => 'required|string|owned_test_case',
        ]);

        $case = TestCaseModel::findOrFail($data['id']);
        $runs = $case->runs()->count();
        $reqs = $case->requirements()->count();
        $case->delete();

        return Response::structured([
            'id' => $data['id'],
            'deleted' => true,
            'runs_deleted' => $runs,
            'requirements_unlinked' => $reqs,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()
                ->description('Test case ULID to delete')
                ->required(),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->required(),
            'deleted' => $schema->boolean()->required(),
            'runs_deleted' => $schema->integer()->required(),
            'requirements_unlinked' => $schema->integer()->required(),
        ];
    }
}
