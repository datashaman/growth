<?php

namespace App\Mcp\Tools\Verification;

use App\Models\TestCase as TestCaseModel;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('List verification cases for a verification plan. For the capabilities a case verifies and the runs/anomalies it produced, use `trace-query` with the case id.')]
class ListVerificationCases extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'test_plan_id' => 'required|string|owned_test_plan',
            'q' => 'nullable|string|max:255',
        ]);

        $query = TestCaseModel::query()->where('test_plan_id', $data['test_plan_id'])->withCount(['requirements', 'runs']);
        if (isset($data['q'])) {
            $query->where('name', 'like', '%'.$data['q'].'%');
        }

        return Response::structured([
            'results' => $query->orderBy('name')->get()->map(fn ($case) => [
                'id' => $case->id,
                'name' => $case->name,
                'objective' => $case->objective,
                'expected_results' => $case->expected_results,
                'capabilities_count' => $case->requirements_count,
                'runs_count' => $case->runs_count,
            ])->all(),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'test_plan_id' => $schema->string()->description('Verification plan ULID')->required(),
            'q' => $schema->string()->description('Substring match on case name'),
        ];
    }
}
