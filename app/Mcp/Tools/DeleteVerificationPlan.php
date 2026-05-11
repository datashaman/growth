<?php

namespace App\Mcp\Tools;

use App\Models\TestPlan;
use App\Models\TestRun;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Delete a verification plan and its cases and runs.')]
class DeleteVerificationPlan extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate(['id' => 'required|string|owned_test_plan']);
        $plan = TestPlan::findOrFail($data['id']);
        $cases = $plan->cases()->count();
        $runs = TestRun::whereIn('test_case_id', $plan->cases()->select('id'))->count();
        $plan->delete();

        return Response::structured(['id' => $data['id'], 'deleted' => true, 'cases_deleted' => $cases, 'runs_deleted' => $runs]);
    }

    public function schema(JsonSchema $schema): array
    {
        return ['id' => $schema->string()->description('Verification plan ULID')->required()];
    }
}
