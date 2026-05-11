<?php

namespace App\Mcp\Tools\Test;

use App\Models\TestCase as TestCaseModel;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Create or update a test case (verification evidence rules LTC). At least one trace to a requirement is required — verification mandates upstream traceability.')]
class UpsertTestCase extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'id' => 'nullable|string|owned_test_case',
            'test_plan_id' => 'required|string|owned_test_plan',
            'name' => 'required|string|max:255',
            'objective' => 'nullable|string',
            'preconditions' => 'nullable|array',
            'inputs' => 'nullable|array',
            'expected_results' => 'required|string',
            'environment' => 'nullable|string',
            'traces_to_requirement_ids' => 'required|array|min:1',
            'traces_to_requirement_ids.*' => 'string|owned_requirement',
        ]);

        $reqIds = $data['traces_to_requirement_ids'];
        unset($data['traces_to_requirement_ids']);
        $id = $data['id'] ?? null;
        unset($data['id']);

        $case = DB::transaction(function () use ($id, $data, $reqIds) {
            $c = $id
                ? tap(TestCaseModel::findOrFail($id))->update($data)
                : TestCaseModel::create($data);

            $c->requirements()->sync($reqIds);

            return $c;
        });

        return Response::structured([
            'id' => $case->id,
            'name' => $case->name,
            'requirements_traced' => count($reqIds),
            'created' => $case->wasRecentlyCreated,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()
                ->description('Existing test case ID. Omit to create new.'),
            'test_plan_id' => $schema->string()
                ->description('Parent test plan ULID')
                ->required(),
            'name' => $schema->string()
                ->description('Test case name')
                ->required(),
            'objective' => $schema->string()
                ->description('What this test case is intended to verify'),
            'preconditions' => $schema->array()
                ->description('Environment/state required before execution'),
            'inputs' => $schema->array()
                ->description('Test inputs '),
            'expected_results' => $schema->string()
                ->description('Expected outcome ')
                ->required(),
            'environment' => $schema->string()
                ->description('Special environment notes'),
            'traces_to_requirement_ids' => $schema->array()
                ->description('Requirement ULIDs this case verifies (rule upstream traceability — at least one required)'),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->required(),
            'name' => $schema->string()->required(),
            'requirements_traced' => $schema->integer()->required(),
            'created' => $schema->boolean()->required(),
        ];
    }
}
