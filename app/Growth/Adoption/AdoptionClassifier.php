<?php

namespace App\Growth\Adoption;

use App\Models\WorkItem;
use Carbon\CarbonInterface;

/**
 * Derives whether a work item's gap predates Growth adoption.
 *
 * "Pre-adoption" is never stored on the work item — it is computed on every
 * evaluation from the project's `adopted_at` timestamp and the work item's
 * witnessed completion, so it cannot fall out of sync.
 *
 * A done work item is pre-adoption when its completion was not witnessed at
 * or after adoption: either it has no recorded transition to `done` (a
 * retro-created history item Growth never saw complete), or its latest such
 * transition is strictly before `adopted_at`. Completion exactly at
 * `adopted_at` counts as post-adoption. When `adopted_at` is null nothing is
 * pre-adoption — the project has always been under Growth.
 *
 * Callers must eager-load `statusTransitions` on the work item.
 */
class AdoptionClassifier
{
    public function isPreAdoption(WorkItem $item, ?CarbonInterface $adoptedAt): bool
    {
        if ($adoptedAt === null) {
            return false;
        }

        $completedAt = $this->completedAt($item);

        return $completedAt === null || $completedAt->lessThan($adoptedAt);
    }

    /**
     * The timestamp Growth witnessed the work item reach `done`, or null when
     * it has no recorded transition to `done`.
     */
    private function completedAt(WorkItem $item): ?CarbonInterface
    {
        return $item->statusTransitions
            ->where('to_status', 'done')
            ->max('transitioned_at');
    }
}
