<?php

namespace App\Mcp\Tools\Plan;

use App\Growth\Transitions\BaselinePlan as BaselinePlanTransition;
use App\Growth\Transitions\IllegalTransitionException;
use App\Models\ProjectPlan;
use App\Models\ProjectPlanBaseline;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Create an immutable baseline snapshot of the current Project Management Plan and its WBS state. Auto-increments version and moves the plan from draft to baselined, recording a status transition. Rejects a plan that is not in draft.')]
class BaselinePlan extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'project_plan_id' => 'required|string|owned_project_plan',
            'note' => 'nullable|string',
        ]);

        try {
            $baseline = DB::transaction(function () use ($data) {
                $plan = ProjectPlan::with([
                    'project.workItems' => fn ($q) => $q->orderBy('kind')->orderBy('name'),
                ])->findOrFail($data['project_plan_id']);

                $version = ((int) $plan->baselines()->max('version')) + 1;

                $baseline = ProjectPlanBaseline::create([
                    'project_plan_id' => $plan->id,
                    'version' => $version,
                    'snapshot' => $this->snapshot($plan),
                    'baselined_at' => now(),
                    'baselined_by_user_id' => auth()->id(),
                    'note' => $data['note'] ?? null,
                ]);

                (new BaselinePlanTransition)->apply($plan, auth()->user(), $data['note'] ?? null);

                return $baseline;
            });
        } catch (IllegalTransitionException $e) {
            return new ResponseFactory(Response::error($e->getMessage()));
        }

        return Response::structured([
            'id' => $baseline->id,
            'project_plan_id' => $baseline->project_plan_id,
            'version' => $baseline->version,
            'baselined_at' => $baseline->baselined_at->toIso8601String(),
            'note' => $baseline->note,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'project_plan_id' => $schema->string()
                ->description('ProjectPlan ULID to baseline')
                ->required(),
            'note' => $schema->string()
                ->description('Optional baseline note / decision record'),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->required(),
            'project_plan_id' => $schema->string()->required(),
            'version' => $schema->integer()->required(),
            'baselined_at' => $schema->string()->required(),
            'note' => $schema->string(),
        ];
    }

    private function snapshot(ProjectPlan $plan): array
    {
        return [
            'project_plan' => [
                'id' => $plan->id,
                'project_id' => $plan->project_id,
                'status' => $plan->status,
                'scope_summary' => $plan->scope_summary,
                'objectives' => $plan->objectives,
                'deliverables_summary' => $plan->deliverables_summary,
                'approach' => $plan->approach,
                'organization_summary' => $plan->organization_summary,
                'assumptions' => $plan->assumptions,
                'constraints' => $plan->constraints,
                'budget_summary' => $plan->budget_summary,
            ],
            'work_items' => $plan->project->workItems->map(fn ($w) => [
                'id' => $w->id,
                'parent_id' => $w->parent_id,
                'responsible_role_id' => $w->responsible_role_id,
                'kind' => $w->kind,
                'name' => $w->name,
                'status' => $w->status,
            ])->values()->all(),
        ];
    }
}
