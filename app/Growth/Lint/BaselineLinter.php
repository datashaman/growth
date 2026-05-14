<?php

namespace App\Growth\Lint;

use App\Growth\Baselines\PlanBaselineDiffer;
use App\Models\Project;
use App\Models\ProjectPlanBaseline;

/**
 * Baseline drift checks for controlled plan/WBS artifacts.
 *
 * Each finding: array{rule, severity, message, subject_type, subject_id}.
 */
class BaselineLinter
{
    public function __construct(private readonly PlanBaselineDiffer $differ) {}

    /**
     * @return list<array{rule:string,severity:string,message:string,subject_type:string,subject_id:string}>
     */
    public function check(Project $project): array
    {
        $plan = $project->projectPlan;
        if (! $plan) {
            return [];
        }

        $baseline = $plan->baselines()
            ->orderByDesc('version')
            ->first();

        if (! $baseline) {
            if ($project->rigor_level >= 3) {
                return [$this->finding(
                    'baseline.none',
                    'warning',
                    "Rigor level {$project->rigor_level} project has no plan baseline",
                    'project_plan',
                    $plan->id,
                )];
            }

            return [];
        }

        return $this->findingsFromDiff($baseline, $this->differ->diff($baseline));
    }

    /**
     * @param  array<string,mixed>  $diff
     * @return list<array{rule:string,severity:string,message:string,subject_type:string,subject_id:string}>
     */
    private function findingsFromDiff(ProjectPlanBaseline $baseline, array $diff): array
    {
        $findings = [];
        foreach (array_merge($diff['project_plan'], $diff['work_items']) as $delta) {
            if ($delta['change_type'] === 'removed') {
                $findings[] = $this->finding(
                    'baseline.artifact.removed',
                    'warning',
                    "Baseline v{$baseline->version}: {$delta['artifact_type']} [{$delta['artifact_id']}] was removed after baseline",
                    $delta['artifact_type'],
                    $delta['artifact_id'],
                );

                continue;
            }

            if (! $delta['covered_by_change']) {
                $findings[] = $this->finding(
                    'baseline.drift.uncovered',
                    'error',
                    "Baseline v{$baseline->version}: {$delta['artifact_type']} [{$delta['artifact_id']}] changed without approved change coverage",
                    $delta['artifact_type'],
                    $delta['artifact_id'],
                );
            }
        }

        return $findings;
    }

    /**
     * @return array{rule:string,severity:string,message:string,subject_type:string,subject_id:string}
     */
    private function finding(string $rule, string $severity, string $message, string $subjectType, string $subjectId): array
    {
        return compact('rule', 'severity', 'message') + [
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
        ];
    }
}
