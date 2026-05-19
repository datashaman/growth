<?php

namespace App\Growth\Plan;

use App\Growth\Transitions\BaselinePlan as BaselinePlanTransition;
use App\Growth\Transitions\IllegalTransitionException;
use App\Models\ProjectPlan;
use App\Models\ProjectPlanBaseline;
use App\Models\User;
use App\Support\AgentContext;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Creates an immutable baseline of a draft plan: snapshots the plan, then
 * moves it `draft` → `baselined`. Shared by the baseline-plan tool and the
 * adopt-project flow, which only differ in the baseline `kind`.
 */
class PlanBaseliner
{
    /**
     * Snapshot the plan and move it to `baselined`.
     *
     * Throws {@see IllegalTransitionException} when the
     * plan is not in `draft`; the snapshot row is rolled back with it.
     */
    public function baseline(
        ProjectPlan $plan,
        ?User $actor,
        ?string $note = null,
        string $kind = 'planned',
    ): ProjectPlanBaseline {
        if (! in_array($kind, ProjectPlanBaseline::KINDS, true)) {
            throw new InvalidArgumentException("Unknown baseline kind [{$kind}].");
        }

        return DB::transaction(function () use ($plan, $actor, $note, $kind): ProjectPlanBaseline {
            $baseline = ProjectPlanBaseline::create([
                'project_plan_id' => $plan->id,
                'version' => ((int) $plan->baselines()->max('version')) + 1,
                'kind' => $kind,
                'snapshot' => $plan->baselineSnapshot(),
                'baselined_at' => now(),
                'baselined_by_user_id' => $actor?->getKey(),
                'baselined_by_agent_id' => app(AgentContext::class)->idForProject($plan->project_id),
                'note' => $note,
            ]);

            (new BaselinePlanTransition)->apply($plan, $actor, $note);

            return $baseline;
        });
    }
}
