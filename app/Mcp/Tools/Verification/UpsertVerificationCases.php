<?php

namespace App\Mcp\Tools\Verification;

use App\Models\TestCase as TestCaseModel;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Throwable;

#[Description('Create or update up to 100 verification cases in one call, syncing the capabilities each case verifies. Each item is committed in its own transaction — per-item validation or runtime failures are reported alongside successes without aborting the batch and without rolling back already-applied items.')]
class UpsertVerificationCases extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $payload = $request->validate([
            'items' => 'required|array|min:1|max:100',
        ], [
            'items.max' => 'Batches are capped at 100 items per call. Split into smaller batches.',
        ]);

        $results = [];
        foreach ($payload['items'] as $index => $item) {
            $results[] = $this->upsertItem((int) $index, is_array($item) ? $item : []);
        }

        return Response::structured(['items' => $results]);
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function upsertItem(int $index, array $item): array
    {
        try {
            $data = Validator::make($item, $this->itemRules())->validate();
        } catch (ValidationException $e) {
            return [
                'index' => $index,
                'ok' => false,
                'errors' => $e->errors(),
            ];
        }

        try {
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

            return [
                'index' => $index,
                'ok' => true,
                'id' => $case->id,
                'name' => $case->name,
                'capabilities_verified' => count($capabilityIds),
                'created' => $case->wasRecentlyCreated,
            ];
        } catch (Throwable $e) {
            return [
                'index' => $index,
                'ok' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * @return array<string, string>
     */
    private function itemRules(): array
    {
        return [
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
        ];
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'items' => $schema->array()
                ->items($schema->object(fn (JsonSchema $s) => [
                    'id' => $s->string()->description('Existing verification case ULID. Omit to create.'),
                    'test_plan_id' => $s->string()->description('Verification plan ULID')->required(),
                    'name' => $s->string()->description('Case name')->required(),
                    'objective' => $s->string()->description('What this case verifies'),
                    'preconditions' => $s->array()->description('Required state before execution'),
                    'inputs' => $s->array()->description('Inputs used during execution'),
                    'expected_results' => $s->string()->description('Expected outcome')->required(),
                    'environment' => $s->string()->description('Environment notes'),
                    'verifies_capability_ids' => $s->array()->description('Capability ULIDs verified by this case')->required(),
                ]))
                ->min(1)
                ->max(100)
                ->description('Up to 100 verification cases to create or update. Items are committed independently; per-item failures are reported in the response without aborting the batch.')
                ->required(),
        ];
    }
}
