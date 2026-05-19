<?php

namespace App\Growth\Assurance;

use App\Growth\Logging\LogLevel;
use App\Growth\Logging\LogReporter;
use App\Growth\Logging\NullLogReporter;
use App\Growth\Progress\NullProgressReporter;
use App\Growth\Progress\ProgressReporter;
use App\Models\Project;
use App\Models\WorkItemDeliveryLink;

class ContradictionScanner
{
    /**
     * @return array<string,mixed>
     */
    public function scan(Project $project, ProgressReporter $progress = new NullProgressReporter, LogReporter $log = new NullLogReporter): array
    {
        $findings = $this->doneWorkAgainstOpenAnomalies($project);
        $progress->report(1, 3, 'Checked done work against open anomalies');
        $log->log(LogLevel::Info, 'Checked done work against open anomalies', ['findings' => count($findings)]);

        $deployed = $this->deployedFailedDeliveryLinks($project);
        $findings = array_merge($findings, $deployed);
        $progress->report(2, 3, 'Checked delivery links deployed despite failed checks');
        $log->log(LogLevel::Info, 'Checked delivery links deployed despite failed checks', ['findings' => count($deployed)]);

        $rejected = $this->implementedRejectedChanges($project);
        $findings = array_merge($findings, $rejected);
        $progress->report(3, 3, 'Checked change requests implemented despite rejection');
        $log->log(LogLevel::Info, 'Checked change requests implemented despite rejection', ['findings' => count($rejected)]);

        $log->log(
            $findings === [] ? LogLevel::Info : LogLevel::Warning,
            $findings === [] ? 'No contradictions found' : count($findings).' contradiction(s) found',
            ['contradictions' => count($findings)],
        );

        return [
            'project_id' => $project->id,
            'contradictions' => count($findings),
            'findings' => $findings,
        ];
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function doneWorkAgainstOpenAnomalies(Project $project): array
    {
        $findings = [];
        $items = $project->workItems()
            ->where('status', 'done')
            ->with('requirements.anomalies')
            ->get();

        foreach ($items as $item) {
            foreach ($item->requirements as $requirement) {
                $openSevere = $requirement->anomalies
                    ->whereIn('severity', ['critical', 'high'])
                    ->whereIn('status', ['open', 'investigating']);

                foreach ($openSevere as $anomaly) {
                    $findings[] = $this->finding(
                        'contradiction.done_work_open_anomaly',
                        'error',
                        "Work item [{$item->name}] is done but requirement [{$requirement->id}] has open {$anomaly->severity} anomaly [{$anomaly->summary}]",
                        'work_item',
                        $item->id,
                        ['requirement_id' => $requirement->id, 'anomaly_id' => $anomaly->id],
                    );
                }
            }
        }

        return $findings;
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function deployedFailedDeliveryLinks(Project $project): array
    {
        $findings = [];
        $links = WorkItemDeliveryLink::query()
            ->whereIn('work_item_id', $project->workItems()->pluck('id'))
            ->with(['workItem:id,name', 'checkRuns', 'deployments'])
            ->get();

        foreach ($links as $link) {
            $failedChecks = $link->checkRuns->whereIn('conclusion', ['failure', 'timed_out', 'action_required']);
            $successfulDeployments = $link->deployments->where('status', 'succeeded');

            if ($failedChecks->isNotEmpty() && $successfulDeployments->isNotEmpty()) {
                $findings[] = $this->finding(
                    'contradiction.deployed_failed_checks',
                    'error',
                    "Delivery link [{$link->type}:{$link->ref}] is deployed despite failed/action-required checks",
                    'work_item_delivery_link',
                    $link->id,
                    [
                        'failed_checks' => $failedChecks->pluck('id')->values()->all(),
                        'deployments' => $successfulDeployments->pluck('id')->values()->all(),
                    ],
                );
            }
        }

        return $findings;
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function implementedRejectedChanges(Project $project): array
    {
        return $project->changeRequests()
            ->where('status', 'implemented')
            ->whereIn('decision', ['rejected', 'deferred'])
            ->get()
            ->map(fn ($change): array => $this->finding(
                'contradiction.implemented_rejected_change',
                'error',
                "Change request [{$change->title}] is implemented despite decision [{$change->decision}]",
                'change_request',
                $change->id,
            ))
            ->all();
    }

    /**
     * @param  array<string,mixed>  $meta
     * @return array<string,mixed>
     */
    private function finding(string $rule, string $severity, string $message, string $subjectType, string $subjectId, array $meta = []): array
    {
        return compact('rule', 'severity', 'message') + [
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'meta' => $meta,
        ];
    }
}
