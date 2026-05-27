<?php

namespace App\Growth\Execution;

use App\Growth\Assurance\MilestoneGateEvaluator;
use App\Growth\WorkItems\WorkItemEvidenceResolver;
use App\Models\Project;
use App\Models\WorkItem;

class ImplementationStatusSummarizer
{
    public function __construct(private readonly WorkItemEvidenceResolver $evidenceResolver) {}

    /**
     * @return array<string,mixed>
     */
    public function summarize(Project $project): array
    {
        $items = $project->workItems()
            ->with(['children:id,parent_id', 'deliveryLinks.checkRuns', 'deliveryLinks.deployments', 'responsibleRole'])
            ->inWbsOrder()
            ->get();

        $rows = $items->map(fn (WorkItem $item): array => $this->summarizeItem($item))->all();

        return [
            'project_id' => $project->id,
            'summary' => [
                'work_items' => count($rows),
                'with_delivery_evidence' => $this->countWhere($rows, fn ($row) => $row['delivery_links'] > 0),
                'with_successful_checks' => $this->countWhere($rows, fn ($row) => $row['successful_checks'] > 0),
                'with_failed_checks' => $this->countWhere($rows, fn ($row) => $row['failed_checks'] > 0),
                'deployed' => $this->countWhere($rows, fn ($row) => $row['successful_deployments'] > 0),
                'done_without_delivery_evidence' => $this->countWhere($rows, fn ($row) => $row['status'] === 'done' && $row['delivery_links'] === 0),
            ],
            'results' => $rows,
        ];
    }

    /**
     * Summarize one work item's delivery evidence — links, checks, deployments,
     * and the derived implementation state. Public so the milestone gate can
     * reuse the same per-item facts (see {@see MilestoneGateEvaluator}).
     *
     * Direct delivery evidence should be eager-loaded by callers that already
     * have it, but aggregate parent items also inherit descendant evidence so
     * rollup containers do not need duplicate PR/check/deployment links.
     *
     * @return array<string,mixed>
     */
    public function summarizeItem(WorkItem $item): array
    {
        $deliveryLinks = $this->evidenceResolver->deliveryLinksFor($item);
        $checks = $deliveryLinks->flatMap->checkRuns;
        $deployments = $deliveryLinks->flatMap->deployments->unique('id');

        return [
            'id' => $item->id,
            'reference' => $item->reference(),
            'kind' => $item->kind,
            'name' => $item->name,
            'status' => $item->status,
            'responsible_role_name' => $item->responsibleRole?->name,
            'delivery_links' => $deliveryLinks->count(),
            'checks' => $checks->count(),
            'successful_checks' => $checks->where('conclusion', 'success')->count(),
            'failed_checks' => $checks->whereIn('conclusion', ['failure', 'timed_out', 'action_required'])->count(),
            'deployments' => $deployments->count(),
            'successful_deployments' => $deployments->where('status', 'succeeded')->count(),
            'implementation_state' => $this->implementationState($item, $deliveryLinks, $checks, $deployments),
        ];
    }

    private function implementationState(WorkItem $item, $deliveryLinks, $checks, $deployments): string
    {
        if ($deployments->where('status', 'succeeded')->isNotEmpty()) {
            return 'deployed';
        }

        if ($checks->whereIn('conclusion', ['failure', 'timed_out', 'action_required'])->isNotEmpty()) {
            return 'blocked_by_checks';
        }

        if ($checks->where('conclusion', 'success')->isNotEmpty()) {
            return 'validated';
        }

        if ($deliveryLinks->isNotEmpty()) {
            return 'implemented';
        }

        return $item->status === 'done' ? 'done_without_evidence' : 'planned';
    }

    /**
     * @param  list<array<string,mixed>>  $rows
     */
    private function countWhere(array $rows, callable $callback): int
    {
        return count(array_filter($rows, $callback));
    }
}
