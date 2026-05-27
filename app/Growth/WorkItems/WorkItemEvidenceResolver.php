<?php

namespace App\Growth\WorkItems;

use App\Models\WorkItem;
use App\Models\WorkItemDeliveryLink;
use Illuminate\Support\Collection;

class WorkItemEvidenceResolver
{
    /**
     * @return Collection<int,WorkItemDeliveryLink>
     */
    public function deliveryLinksFor(WorkItem $workItem): Collection
    {
        $directLinks = $workItem->relationLoaded('deliveryLinks')
            ? $workItem->deliveryLinks
            : $workItem->deliveryLinks()->with('checkRuns', 'deployments')->get();

        $descendantIds = $this->descendantIds($workItem);

        if ($descendantIds === []) {
            return $directLinks->unique('id')->values();
        }

        $descendantLinks = WorkItem::query()
            ->whereKey($descendantIds)
            ->with('deliveryLinks.checkRuns', 'deliveryLinks.deployments')
            ->get()
            ->flatMap->deliveryLinks;

        return $directLinks
            ->concat($descendantLinks)
            ->unique('id')
            ->values();
    }

    public function hasDeliveryEvidence(WorkItem $workItem): bool
    {
        return $this->deliveryLinksFor($workItem)->isNotEmpty();
    }

    /**
     * @return list<string>
     */
    private function descendantIds(WorkItem $workItem): array
    {
        $ids = [];
        $frontier = $workItem->relationLoaded('children')
            ? $workItem->children->pluck('id')->all()
            : $workItem->children()->pluck('id')->all();

        while ($frontier !== []) {
            array_push($ids, ...$frontier);

            $frontier = WorkItem::query()
                ->whereIn('parent_id', $frontier)
                ->pluck('id')
                ->all();
        }

        return $ids;
    }
}
