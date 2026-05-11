<?php

namespace App\Growth\Assurance;

use App\Growth\Execution\ImplementationStatusSummarizer;
use App\Growth\Lint\BaselineLinter;
use App\Growth\Lint\ChangeLinter;
use App\Growth\Lint\DesignLinter;
use App\Growth\Lint\PmpLinter;
use App\Growth\Lint\RequirementLinter;
use App\Growth\Lint\ReviewLinter;
use App\Growth\Lint\TestLinter;
use App\Models\Project;

class ReadinessGateEvaluator
{
    public function __construct(
        private readonly RequirementLinter $requirementLinter,
        private readonly DesignLinter $designLinter,
        private readonly TestLinter $testLinter,
        private readonly PmpLinter $pmpLinter,
        private readonly ReviewLinter $reviewLinter,
        private readonly ChangeLinter $changeLinter,
        private readonly BaselineLinter $baselineLinter,
        private readonly ImplementationStatusSummarizer $implementationStatus,
    ) {}

    /**
     * @return array<string,mixed>
     */
    public function evaluate(Project $project): array
    {
        $requirementFindings = [];
        foreach ($project->requirements as $requirement) {
            array_push($requirementFindings, ...$this->requirementLinter->check($requirement));
        }

        $implementation = $this->implementationStatus->summarize($project);
        $implementationFindings = [];
        if (($implementation['summary']['with_failed_checks'] ?? 0) > 0) {
            $implementationFindings[] = [
                'rule' => 'implementation.checks.failed',
                'severity' => 'error',
                'message' => 'One or more work items have failed, timed-out, or action-required checks.',
            ];
        }
        if (($implementation['summary']['done_without_delivery_evidence'] ?? 0) > 0) {
            $implementationFindings[] = [
                'rule' => 'implementation.done_without_evidence',
                'severity' => 'warning',
                'message' => 'One or more done work items have no delivery evidence.',
            ];
        }

        $gates = [
            $this->gate('capabilities', 'Capabilities are clear, verifiable, and accepted enough to plan.', $requirementFindings),
            $this->gate('architecture', 'Design evidence is coherent for the stated concerns.', $this->designLinter->check($project)),
            $this->gate('verification', 'Test plans, cases, runs, and anomaly posture support verification.', $this->testLinter->check($project)),
            $this->gate('planning', 'PMP, WBS, schedule, risks, and responsibilities are ready.', $this->pmpLinter->check($project)),
            $this->gate('review', 'Review and audit evidence is sufficient for the project rigor level.', $this->reviewLinter->check($project)),
            $this->gate('change_control', 'Baselines and change requests are controlled.', array_merge(
                $this->baselineLinter->check($project),
                $this->changeLinter->check($project),
            )),
            $this->gate('implementation', 'Delivery evidence, checks, and deployment state support release readiness.', $implementationFindings),
        ];

        return [
            'project_id' => $project->id,
            'status' => $this->overallStatus($gates),
            'gates' => $gates,
            'implementation_summary' => $implementation['summary'],
        ];
    }

    /**
     * @param  list<array<string,mixed>>  $findings
     * @return array<string,mixed>
     */
    private function gate(string $id, string $description, array $findings): array
    {
        $errors = count(array_filter($findings, fn (array $finding): bool => $finding['severity'] === 'error'));
        $warnings = count(array_filter($findings, fn (array $finding): bool => $finding['severity'] !== 'error'));

        return [
            'id' => $id,
            'description' => $description,
            'status' => $errors > 0 ? 'fail' : ($warnings > 0 ? 'warn' : 'pass'),
            'errors' => $errors,
            'warnings' => $warnings,
            'findings' => $findings,
        ];
    }

    /**
     * @param  list<array<string,mixed>>  $gates
     */
    private function overallStatus(array $gates): string
    {
        if (collect($gates)->contains('status', 'fail')) {
            return 'fail';
        }

        return collect($gates)->contains('status', 'warn') ? 'warn' : 'pass';
    }
}
