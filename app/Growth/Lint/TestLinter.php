<?php

namespace App\Growth\Lint;

use App\Models\Project;

/**
 * verification evidence-2008 test documentation completeness checks.
 *
 * Plans should declare scope and approach . Cases should trace
 * back to at least one requirement . Critical anomalies should
 * not linger in open/investigating state.
 *
 * Each finding: array{rule, severity, message, subject_type, subject_id}.
 */
class TestLinter
{
    /**
     * @return list<array{rule:string,severity:string,message:string,subject_type:string,subject_id:string}>
     */
    public function check(Project $project): array
    {
        $findings = [];

        $plans = $project->testPlans()->with(['cases.requirements'])->get();

        $hasSubordinatePlan = $plans->where('level', '!=', 'master')->isNotEmpty();

        foreach ($plans as $plan) {
            $isMaster = $plan->level === 'master';

            if (empty($plan->scope)) {
                $findings[] = $this->finding(
                    'plan-no-scope', 'warning',
                    "rule: verification plan [{$plan->name}] has no declared scope",
                    'test_plan', $plan->id,
                );
            }
            if (empty($plan->approach)) {
                $findings[] = $this->finding(
                    'plan-no-approach', 'warning',
                    "rule: verification plan [{$plan->name}] has no declared approach",
                    'test_plan', $plan->id,
                );
            }
            // Master plans are organizing documents (strategy, scope, roll-up
            // criteria) and intentionally hold no test cases. TODO: once
            // verification plans gain an optional parent_plan_id, replace the
            // project-wide $hasSubordinatePlan heuristic with a check on the
            // master plan's actual children.
            if (! $isMaster && $plan->cases->isEmpty()) {
                $findings[] = $this->finding(
                    'plan-empty', 'warning',
                    "rule: verification plan [{$plan->name}] has no test cases",
                    'test_plan', $plan->id,
                );
            }
            if ($isMaster && ! $hasSubordinatePlan) {
                $findings[] = $this->finding(
                    'master-no-subordinates', 'warning',
                    "rule: master verification plan [{$plan->name}] has no subordinate plans",
                    'test_plan', $plan->id,
                );
            }

            foreach ($plan->cases as $case) {
                if ($case->requirements->isEmpty()) {
                    $findings[] = $this->finding(
                        'case-untraced', 'warning',
                        "rule: test case [{$case->name}] is not traced to any requirement",
                        'test_case', $case->id,
                    );
                }
            }
        }

        $openCritical = $project->anomalies()
            ->where('severity', 'critical')
            ->whereIn('status', ['open', 'investigating'])
            ->get();

        foreach ($openCritical as $a) {
            $findings[] = $this->finding(
                'critical-open', 'error',
                "rule: critical anomaly [{$a->summary}] is still {$a->status}",
                'anomaly', $a->id,
            );
        }

        if ($plans->isEmpty()) {
            $findings[] = $this->finding(
                'no-plans', 'error',
                'rule: project has no verification plans',
                'project', $project->id,
            );
        } elseif ($plans->where('level', 'master')->isEmpty()) {
            $findings[] = $this->finding(
                'no-master', 'warning',
                'rule: project has no master verification plan',
                'project', $project->id,
            );
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
