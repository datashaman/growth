<?php

namespace App\Growth\Transitions;

use App\Growth\Assurance\MilestoneGateEvaluator;
use Illuminate\Database\Eloquent\Model;

/**
 * Mark a milestone as achieved: `pending` → `achieved`.
 *
 * Gated (#200): a milestone may only be achieved once its readiness gate
 * passes — every member work item `done` with clean delivery evidence, and at
 * least one work item linked. {@see MilestoneGateEvaluator}.
 */
class AchieveMilestone extends Transition
{
    public function allowedFrom(): array
    {
        return ['pending'];
    }

    public function targetStatus(): string
    {
        return 'achieved';
    }

    public function verb(): string
    {
        return 'achieve';
    }

    public function subjectLabel(): string
    {
        return 'milestone';
    }

    protected function assertPreconditions(Model $subject): void
    {
        $gate = app(MilestoneGateEvaluator::class)->evaluate($subject);

        if ($gate['status'] === 'fail') {
            throw new IllegalTransitionException($this->gateRejectionMessage($gate));
        }
    }

    /**
     * Summarise the blocking findings into one clear sentence the agent can act on.
     *
     * @param  array{findings:list<array<string,mixed>>}  $gate
     */
    private function gateRejectionMessage(array $gate): string
    {
        $byRule = array_count_values(array_map(
            fn (array $finding): string => $finding['rule'],
            array_filter($gate['findings'], fn (array $finding): bool => $finding['severity'] === 'error'),
        ));

        $clauses = [];

        if (isset($byRule['milestone.empty'])) {
            $clauses[] = 'it has no member work items — link the work items it bundles first';
        }

        if ($count = $byRule['milestone.work_item.not_done'] ?? 0) {
            $clauses[] = $count.' member work item'.($count === 1 ? ' is' : 's are').' not done';
        }

        if ($count = $byRule['milestone.work_item.failed_checks'] ?? 0) {
            $clauses[] = $count.' done member work item'.($count === 1 ? ' has' : 's have').' failed checks';
        }

        if ($count = $byRule['milestone.work_item.project_mismatch'] ?? 0) {
            $clauses[] = $count.' member work item'.($count === 1 ? ' belongs' : 's belong').' to a different project';
        }

        if ($clauses === []) {
            $clauses[] = 'it has unresolved blocking gate findings';
        }

        return 'Cannot achieve a milestone until its gate passes: '.implode('; ', $clauses).'.';
    }
}
