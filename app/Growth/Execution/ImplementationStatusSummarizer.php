<?php

namespace App\Growth\Execution;

use App\Models\Project;
use App\Models\WorkItem;

class ImplementationStatusSummarizer
{
    /**
     * @return array<string,mixed>
     */
    public function summarize(Project $project): array
    {
        $items = $project->workItems()
            ->with(['deliveryLinks.checkRuns', 'deliveryLinks.deployments'])
            ->orderBy('kind')
            ->orderBy('name')
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
     * @return array<string,mixed>
     */
    private function summarizeItem(WorkItem $item): array
    {
        $checks = $item->deliveryLinks->flatMap->checkRuns;
        $deployments = $item->deliveryLinks->flatMap->deployments->unique('id');

        return [
            'id' => $item->id,
            'kind' => $item->kind,
            'name' => $item->name,
            'status' => $item->status,
            'delivery_links' => $item->deliveryLinks->count(),
            'checks' => $checks->count(),
            'successful_checks' => $checks->where('conclusion', 'success')->count(),
            'failed_checks' => $checks->whereIn('conclusion', ['failure', 'timed_out', 'action_required'])->count(),
            'deployments' => $deployments->count(),
            'successful_deployments' => $deployments->where('status', 'succeeded')->count(),
            'implementation_state' => $this->implementationState($item, $checks, $deployments),
        ];
    }

    private function implementationState(WorkItem $item, $checks, $deployments): string
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

        if ($item->deliveryLinks->isNotEmpty()) {
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
