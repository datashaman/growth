<?php

namespace App\Mcp\Tools;

use App\Models\TestCase as TestCaseModel;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Create or update a verification case and sync the capabilities it verifies.')]
class UpsertVerificationCase extends Tool
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
            'verifies_capability_ids' => 'required|array|min:1',
            'verifies_capability_ids.*' => 'string|owned_requirement',
        ]);

        $id = $data['id'] ?? null;
        $capabilityIds = $data['verifies_capability_ids'];
        unset($data['id'], $data['verifies_capability_ids']);

        $case = DB::transaction(function () use ($id, $data, $capabilityIds) {
            $case = $id
                ? tap(TestCaseModel::findOrFail($id))->update($data)
                : TestCaseModel::create($data);

            $case->requirements()->sync($capabilityIds);

            return $case;
        });

        return Response::structured([
            'id' => $case->id,
            'name' => $case->name,
            'capabilities_verified' => count($capabilityIds),
            'created' => $case->wasRecentlyCreated,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->description('Existing verification case ULID. Omit to create.'),
            'test_plan_id' => $schema->string()->description('Verification plan ULID')->required(),
            'name' => $schema->string()->description('Case name')->required(),
            'objective' => $schema->string()->description('What this case verifies'),
            'preconditions' => $schema->array()->description('Required state before execution'),
            'inputs' => $schema->array()->description('Inputs used during execution'),
            'expected_results' => $schema->string()->description('Expected outcome')->required(),
            'environment' => $schema->string()->description('Environment notes'),
            'verifies_capability_ids' => $schema->array()->description('Capability ULIDs verified by this case')->required(),
        ];
    }
}
