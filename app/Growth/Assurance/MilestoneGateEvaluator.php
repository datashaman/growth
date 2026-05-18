<?php

namespace App\Growth\Assurance;

use App\Growth\Adoption\AdoptionClassifier;
use App\Growth\Execution\ImplementationStatusSummarizer;
use App\Models\Milestone;

/**
 * Evaluates a milestone's readiness gate (#200).
 *
 * A milestone is a named scope bundle: a set of work items that together
 * deliver something. Its gate passes when every member work item is `done`
 * and carries clean delivery evidence — failed checks block, a done item with
 * no delivery evidence warns (or, when it was completed before Growth
 * adoption, is reported as informational). An empty milestone bundles nothing
 * and fails.
 *
 * The result is consumed two ways: as a precondition on the AchieveMilestone
 * transition (a `fail` blocks the transition) and as a read-only field on
 * `list-milestones` so an agent can see readiness without attempting it.
 */
class MilestoneGateEvaluator
{
    public function __construct(
        private readonly ImplementationStatusSummarizer $summarizer,
        private readonly AdoptionClassifier $adoptionClassifier,
    ) {}

    /**
     * @return array{milestone_id:string,status:string,work_items:int,errors:int,warnings:int,findings:list<array<string,mixed>>}
     */
    public function evaluate(Milestone $milestone): array
    {
        $milestone->loadMissing([
            'project',
            'workItems.deliveryLinks.checkRuns',
            'workItems.deliveryLinks.deployments',
            // The adoption classifier only needs each work item's `done`
            // transitions — constrain the load so a long status history does
            // not balloon the query payload.
            'workItems.statusTransitions' => fn ($query) => $query
                ->where('to_status', 'done')
                ->select('transitionable_type', 'transitionable_id', 'to_status', 'transitioned_at'),
        ]);

        $items = $milestone->workItems;
        $findings = [];

        if ($items->isEmpty()) {
            $findings[] = [
                'rule' => 'milestone.empty',
                'severity' => 'error',
                'message' => 'Milestone has no member work items; link the work items it bundles before achieving it.',
                'subject_type' => 'milestone',
                'subject_id' => $milestone->id,
            ];
        }

        foreach ($items as $item) {
            if ($item->project_id !== $milestone->project_id) {
                $findings[] = [
                    'rule' => 'milestone.work_item.project_mismatch',
                    'severity' => 'error',
                    'message' => 'Member work item belongs to a different project; unlink it from this milestone.',
                    'subject_type' => 'work_item',
                    'subject_id' => $item->id,
                ];

                continue;
            }

            $row = $this->summarizer->summarizeItem($item);

            if ($row['status'] !== 'done') {
                $findings[] = [
                    'rule' => 'milestone.work_item.not_done',
                    'severity' => 'error',
                    'message' => "Member work item is {$row['status']}, not done.",
                    'subject_type' => 'work_item',
                    'subject_id' => $item->id,
                ];

                continue;
            }

            if ($row['failed_checks'] > 0) {
                $findings[] = [
                    'rule' => 'milestone.work_item.failed_checks',
                    'severity' => 'error',
                    'message' => 'Done member work item has failed, timed-out, or action-required checks.',
                    'subject_type' => 'work_item',
                    'subject_id' => $item->id,
                ];
            }

            if ($row['delivery_links'] === 0) {
                $preAdoption = $this->adoptionClassifier->isPreAdoption(
                    $item,
                    $milestone->project?->adopted_at,
                );

                $findings[] = [
                    'rule' => 'milestone.work_item.done_without_evidence',
                    'severity' => $preAdoption ? 'informational' : 'warning',
                    'message' => $preAdoption
                        ? 'Done member work item has no delivery evidence; completed before Growth adoption.'
                        : 'Done member work item has no delivery evidence.',
                    'subject_type' => 'work_item',
                    'subject_id' => $item->id,
                ];
            }
        }

        // `informational` findings (pre-adoption gaps) are reported but never
        // counted — they must not move the gate off `pass`.
        $errors = count(array_filter($findings, fn (array $finding): bool => $finding['severity'] === 'error'));
        $warnings = count(array_filter($findings, fn (array $finding): bool => $finding['severity'] === 'warning'));

        return [
            'milestone_id' => $milestone->id,
            'status' => $errors > 0 ? 'fail' : ($warnings > 0 ? 'warn' : 'pass'),
            'work_items' => $items->count(),
            'errors' => $errors,
            'warnings' => $warnings,
            'findings' => $findings,
        ];
    }
}
