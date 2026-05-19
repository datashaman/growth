<?php

namespace App\Growth\Assurance;

use App\Growth\Adoption\AdoptionClassifier;
use App\Growth\Execution\ImplementationStatusSummarizer;
use App\Growth\Lint\BaselineLinter;
use App\Growth\Lint\ChangeLinter;
use App\Growth\Lint\DesignLinter;
use App\Growth\Lint\PmpLinter;
use App\Growth\Lint\RequirementLinter;
use App\Growth\Lint\ReviewLinter;
use App\Growth\Lint\TestLinter;
use App\Growth\Progress\NullProgressReporter;
use App\Growth\Progress\ProgressReporter;
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
        private readonly AdoptionClassifier $adoptionClassifier,
    ) {}

    /**
     * @return array<string,mixed>
     */
    public function evaluate(Project $project, ProgressReporter $progress = new NullProgressReporter): array
    {
        $requirementFindings = [];
        foreach ($project->requirements as $requirement) {
            array_push($requirementFindings, ...$this->requirementLinter->check($requirement));
        }

        $implementation = $this->implementationStatus->summarize($project);

        // Only a done work item with no delivery evidence needs an adoption
        // check; load just those, and only their `done` transition trail —
        // the rest of the project's work items never reach the classifier.
        $gapWorkItemIds = collect($implementation['results'])
            ->filter(fn (array $row): bool => $row['status'] === 'done' && $row['delivery_links'] === 0)
            ->pluck('id');

        $gapWorkItems = $gapWorkItemIds->isEmpty()
            ? collect()
            : $project->workItems()
                ->whereKey($gapWorkItemIds)
                ->with(['statusTransitions' => fn ($query) => $query
                    ->where('to_status', 'done')
                    ->select('transitionable_type', 'transitionable_id', 'to_status', 'transitioned_at')])
                ->get()
                ->keyBy('id');

        $implementationFindings = [];
        foreach ($implementation['results'] as $row) {
            if ($row['failed_checks'] > 0) {
                $implementationFindings[] = [
                    'rule' => 'implementation.checks.failed',
                    'severity' => 'error',
                    'message' => 'Work item has failed, timed-out, or action-required checks',
                    'subject_type' => 'work_item',
                    'subject_id' => $row['id'],
                ];
            }
            if ($row['status'] === 'done' && $row['delivery_links'] === 0) {
                $workItem = $gapWorkItems->get($row['id']);
                $preAdoption = $workItem !== null
                    && $this->adoptionClassifier->isPreAdoption($workItem, $project->adopted_at);

                $implementationFindings[] = [
                    'rule' => 'implementation.done_without_evidence',
                    'severity' => $preAdoption ? 'informational' : 'warning',
                    'message' => $preAdoption
                        ? 'Done work item has no delivery evidence; completed before Growth adoption'
                        : 'Done work item has no delivery evidence',
                    'subject_type' => 'work_item',
                    'subject_id' => $row['id'],
                ];
            }
        }

        $gates = [];

        $gates[] = $this->gate('requirements', 'Requirements are clear, verifiable, and accepted enough to plan.', $requirementFindings);
        $progress->report(1, 7, 'Evaluated requirements gate');

        $gates[] = $this->gate('architecture', 'Design evidence is coherent for the stated concerns.', $this->designLinter->check($project));
        $progress->report(2, 7, 'Evaluated architecture gate');

        $gates[] = $this->gate('verification', 'Test plans, cases, runs, and anomaly posture support verification.', $this->testLinter->check($project));
        $progress->report(3, 7, 'Evaluated verification gate');

        $gates[] = $this->gate('planning', 'PMP, WBS, schedule, risks, and responsibilities are ready.', $this->pmpLinter->check($project));
        $progress->report(4, 7, 'Evaluated planning gate');

        $gates[] = $this->gate('review', 'Review and audit evidence is sufficient for the project rigor level.', $this->reviewLinter->check($project));
        $progress->report(5, 7, 'Evaluated review gate');

        $gates[] = $this->gate('change_control', 'Baselines and change requests are controlled.', array_merge(
            $this->baselineLinter->check($project),
            $this->changeLinter->check($project),
        ));
        $progress->report(6, 7, 'Evaluated change control gate');

        $gates[] = $this->gate('implementation', 'Delivery evidence, checks, and deployment state support release readiness.', $implementationFindings);
        $progress->report(7, 7, 'Evaluated implementation gate');

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
        // `informational` findings (pre-adoption gaps) are reported but never
        // counted — they must not move the gate off `pass`.
        $errors = count(array_filter($findings, fn (array $finding): bool => $finding['severity'] === 'error'));
        $warnings = count(array_filter($findings, fn (array $finding): bool => $finding['severity'] === 'warning'));

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
