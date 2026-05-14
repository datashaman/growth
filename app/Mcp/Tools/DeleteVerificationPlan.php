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

#[Description('Delete a verification plan. Cascades: cases delete, each case takes its runs; anomalies whose test_run_id pointed at a deleted run have that field nulled. Requires confirm_name to match the plan name exactly.')]
class DeleteVerificationPlan extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'id' => 'required|string|owned_test_plan',
            'confirm_name' => 'required|string',
        ]);

        $plan = TestPlan::findOrFail($data['id']);

        if ($data['confirm_name'] !== $plan->name) {
            return new ResponseFactory(Response::error(
                "Confirmation mismatch. Pass the plan's exact name in `confirm_name` to delete it. Plan is named [{$plan->name}]."
            ));
        }

        $cases = $plan->cases()->count();
        $runs = TestRun::whereIn('test_case_id', $plan->cases()->select('id'))->count();
        $plan->delete();

        return Response::structured(['id' => $data['id'], 'deleted' => true, 'cases_deleted' => $cases, 'runs_deleted' => $runs]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->description('Verification plan ULID')->required(),
            'confirm_name' => $schema->string()
                ->description('Must match the plan name exactly to guard against accidental deletion')
                ->required(),
        ];
    }
}
