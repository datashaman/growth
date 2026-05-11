<?php

namespace App\Mcp\Tools\Test;

use App\Models\TestPlan;
use App\Models\TestRun;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Delete a test plan (verification evidence). Its test cases cascade-delete; each case takes its runs with it. Anomalies whose test_run_id pointed at a deleted run have that field set to null.')]
class DeleteTestPlan extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'id' => 'required|string|owned_test_plan',
        ]);

        $plan = TestPlan::findOrFail($data['id']);
        $caseCount = $plan->cases()->count();
        $runCount = TestRun::whereIn('test_case_id', $plan->cases()->select('id'))->count();
        $plan->delete();

        return Response::structured([
            'id' => $data['id'],
            'deleted' => true,
            'cases_deleted' => $caseCount,
            'runs_deleted' => $runCount,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()
                ->description('Test plan ULID to delete')
                ->required(),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->required(),
            'deleted' => $schema->boolean()->required(),
            'cases_deleted' => $schema->integer()->required(),
            'runs_deleted' => $schema->integer()->required(),
        ];
    }
}
