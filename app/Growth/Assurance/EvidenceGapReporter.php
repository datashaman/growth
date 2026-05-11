<?php

namespace App\Growth\Assurance;

use App\Models\Project;

class EvidenceGapReporter
{
    /**
     * @return array<string,mixed>
     */
    public function report(Project $project): array
    {
        $findings = array_merge(
            $this->doneWorkWithoutDeliveryEvidence($project),
            $this->reviewDecisionsWithoutEvents($project),
            $this->changeDecisionsWithoutEvents($project),
            $this->releasedWorkWithoutDeployment($project),
        );

        return [
            'project_id' => $project->id,
            'gaps' => count($findings),
            'findings' => $findings,
        ];
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function doneWorkWithoutDeliveryEvidence(Project $project): array
    {
        return $project->workItems()
            ->where('status', 'done')
            ->doesntHave('deliveryLinks')
            ->get()
            ->map(fn ($item): array => $this->finding(
                'evidence.work_item.done_without_delivery',
                'warning',
                "Done work item [{$item->name}] has no delivery evidence link",
                'work_item',
                $item->id,
            ))
            ->all();
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function reviewDecisionsWithoutEvents(Project $project): array
    {
        return $project->reviews()
            ->whereNotNull('decision')
            ->doesntHave('decisionEvents')
            ->get()
            ->map(fn ($review): array => $this->finding(
                'evidence.review.decision_without_event',
                'warning',
                "Review [{$review->title}] has a decision but no decision audit event",
                'review',
                $review->id,
            ))
            ->all();
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function changeDecisionsWithoutEvents(Project $project): array
    {
        return $project->changeRequests()
            ->whereNotNull('decision')
            ->doesntHave('approvalEvents')
            ->get()
            ->map(fn ($change): array => $this->finding(
                'evidence.change.decision_without_event',
                'warning',
                "Change request [{$change->title}] has a decision but no approval audit event",
                'change_request',
                $change->id,
            ))
            ->all();
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function releasedWorkWithoutDeployment(Project $project): array
    {
        $findings = [];
        $releases = $project->releases()
            ->where('status', 'released')
            ->with('workItems.deliveryLinks.deployments')
            ->get();

        foreach ($releases as $release) {
            foreach ($release->workItems as $item) {
                $deployed = $item->deliveryLinks
                    ->flatMap->deployments
                    ->where('status', 'succeeded')
                    ->isNotEmpty();

                if (! $deployed) {
                    $findings[] = $this->finding(
                        'evidence.release.work_item_not_deployed',
                        'warning',
                        "Released work item [{$item->name}] in release [{$release->version}] has no successful deployment evidence",
                        'work_item',
                        $item->id,
                        ['release_id' => $release->id],
                    );
                }
            }
        }

        return $findings;
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
