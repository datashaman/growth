<?php

namespace App\Console\Commands;

use App\Models\WorkItemDeliveryLink;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

#[Signature('delivery-links:canonicalize-refs')]
#[Description('Collapse pull-request delivery links whose refs differ only in form (14, #14, PR-14, a /pull/14 URL) into one canonical #<number> row per work item, preserving every linked check run, deployment, and evidence asset.')]
class CanonicalizeDeliveryLinkRefs extends Command
{
    public function handle(): int
    {
        // Runs unauthenticated, so the ScopedByOwner global scope is a no-op
        // and the pass covers every workspace. Only pull_request and evidence
        // refs canonicalise; branch and commit refs are stored verbatim. The
        // work items are walked in bounded id-keyed chunks to cap memory; each
        // work item holds only a handful of delivery links, loaded together so
        // the whole canonical group is in hand.
        $merged = 0;
        $rewritten = 0;

        WorkItemDeliveryLink::query()
            ->whereIn('type', ['pull_request', 'evidence'])
            ->select('work_item_id')
            ->distinct()
            ->lazyById(200, 'work_item_id')
            ->each(function (WorkItemDeliveryLink $row) use (&$merged, &$rewritten): void {
                $links = WorkItemDeliveryLink::query()
                    ->where('work_item_id', $row->work_item_id)
                    ->whereIn('type', ['pull_request', 'evidence'])
                    ->get();

                $groups = $links->groupBy(
                    fn (WorkItemDeliveryLink $link) => $link->type.'|'.WorkItemDeliveryLink::canonicalRef($link->type, $link->ref)
                );

                foreach ($groups as $group) {
                    $canonical = WorkItemDeliveryLink::canonicalRef($group->first()->type, $group->first()->ref);

                    // Singleton already in canonical form: nothing to do.
                    if ($group->count() === 1 && $group->first()->ref === $canonical) {
                        continue;
                    }

                    $result = $this->collapseGroup($group, $canonical);
                    $merged += $result['merged'];
                    $rewritten += $result['rewritten'];
                }
            });

        $this->info("Merged {$merged} duplicate delivery link(s); rewrote {$rewritten} ref(s) to canonical form.");

        return self::SUCCESS;
    }

    /**
     * Collapse one canonical group to a single row. The survivor is the row
     * already carrying the canonical ref, else the oldest, so child
     * reassignment is minimised. Children move onto the survivor before the
     * duplicates are deleted (the survivor's ref is set last, once the
     * duplicates are gone, so the (work_item_id, type, ref) unique index
     * cannot collide). The whole group is one transaction so a crash leaves it
     * re-runnable rather than half-canonicalised.
     *
     * @param  Collection<int,WorkItemDeliveryLink>  $group
     * @return array{merged:int,rewritten:int}
     */
    private function collapseGroup($group, string $canonical): array
    {
        return DB::transaction(function () use ($group, $canonical): array {
            $survivor = $group->firstWhere('ref', $canonical) ?? $group->sortBy('created_at')->first();
            $duplicates = $group->reject(fn (WorkItemDeliveryLink $link) => $link->is($survivor));

            foreach ($duplicates as $duplicate) {
                $this->reassignChildren($duplicate, $survivor);
                $duplicate->delete();
            }

            $rewritten = 0;
            if ($survivor->ref !== $canonical) {
                $survivor->update(['ref' => $canonical]);
                $rewritten = 1;
            }

            return ['merged' => $duplicates->count(), 'rewritten' => $rewritten];
        });
    }

    /**
     * Move a duplicate's check runs, deployments, and evidence assets onto the
     * survivor as a union — no child is lost. Check-run evidence is uniquely
     * keyed by (delivery link, provider, name); when the survivor already has
     * that provider/name the duplicate's row is the redundant one and is
     * dropped, so the survivor's existing evidence wins. Evidence assets are
     * reassigned before the duplicate is deleted so its `deleting` hook (which
     * tears down the S3 objects) finds none to remove.
     */
    private function reassignChildren(WorkItemDeliveryLink $duplicate, WorkItemDeliveryLink $survivor): void
    {
        $existingCheckRuns = $survivor->checkRuns()
            ->get(['provider', 'name'])
            ->map(fn ($run) => $run->provider.'|'.$run->name)
            ->all();

        foreach ($duplicate->checkRuns as $checkRun) {
            if (in_array($checkRun->provider.'|'.$checkRun->name, $existingCheckRuns, true)) {
                $checkRun->delete();

                continue;
            }

            $checkRun->update(['work_item_delivery_link_id' => $survivor->id]);
            $existingCheckRuns[] = $checkRun->provider.'|'.$checkRun->name;
        }

        $survivor->deployments()->syncWithoutDetaching($duplicate->deployments->pluck('id')->all());
        $duplicate->deployments()->detach();

        $duplicate->evidenceAssets()->update(['work_item_delivery_link_id' => $survivor->id]);
    }
}
