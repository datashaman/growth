<?php

namespace App\Mcp\Tools\Verification;

use App\Models\TestPlan;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('List verification plans for a project.')]
class ListVerificationPlans extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'project_id' => 'required|string|owned_project',
            'level' => 'nullable|string|in:'.implode(',', TestPlan::LEVELS),
            'q' => 'nullable|string|max:255',
        ]);

        $query = TestPlan::query()->where('project_id', $data['project_id'])->withCount('cases');
        if (isset($data['level'])) {
            $query->where('level', $data['level']);
        }
        if (isset($data['q'])) {
            $query->where('name', 'like', '%'.$data['q'].'%');
        }

        return Response::structured([
            'results' => $query->orderBy('level')->orderBy('name')->get()->map(fn ($plan) => [
                'id' => $plan->id,
                'level' => $plan->level,
                'name' => $plan->name,
                'scope' => $plan->scope,
                'approach' => $plan->approach,
                'cases_count' => $plan->cases_count,
            ])->all(),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'project_id' => $schema->string()->description('Project ULID')->required(),
            'level' => $schema->string()->description('Filter by verification level')->enum(TestPlan::LEVELS),
            'q' => $schema->string()->description('Substring match on plan name'),
        ];
    }
}
