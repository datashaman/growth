<?php

namespace App\Mcp\Tools\Verification;

use App\Models\TestPlan;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Create or update a verification plan.')]
class UpsertVerificationPlan extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'id' => 'nullable|string|owned_test_plan',
            'project_id' => 'required|string|owned_project',
            'level' => 'required|string|in:'.implode(',', TestPlan::LEVELS),
            'name' => 'required|string|max:255',
            'scope' => 'nullable|string',
            'approach' => 'nullable|string',
            'pass_fail_criteria' => 'nullable|string',
        ]);

        $id = $data['id'] ?? null;
        unset($data['id']);

        $plan = $id ? tap(TestPlan::findOrFail($id))->update($data) : TestPlan::create($data);

        return Response::structured([
            'id' => $plan->id,
            'level' => $plan->level,
            'name' => $plan->name,
            'created' => $plan->wasRecentlyCreated,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->description('Existing verification plan ULID. Omit to create.'),
            'project_id' => $schema->string()->description('Project ULID')->required(),
            'level' => $schema->string()->description('Verification level')->enum(TestPlan::LEVELS)->required(),
            'name' => $schema->string()->description('Plan name')->required(),
            'scope' => $schema->string()->description('What this plan covers'),
            'approach' => $schema->string()->description('Verification strategy and approach'),
            'pass_fail_criteria' => $schema->string()->description('Pass/fail criteria'),
        ];
    }
}
