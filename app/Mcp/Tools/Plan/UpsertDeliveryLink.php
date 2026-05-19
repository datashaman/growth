<?php

namespace App\Mcp\Tools\Plan;

use App\Models\UnattributedGithubEvent;
use App\Models\WorkItemDeliveryLink;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[IsDestructive(false)]
#[Description('Create or update an implementation evidence link from a work item to a commit, pull request, branch, or visual-evidence gallery.')]
class UpsertDeliveryLink extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'id' => 'nullable|string|owned_work_item_delivery_link',
            'work_item_id' => 'required|string|owned_work_item',
            'type' => 'required|in:'.implode(',', WorkItemDeliveryLink::TYPES),
            'ref' => 'required|string|max:255',
            'url' => 'nullable|url|max:2048',
            'description' => 'nullable|string',
        ]);

        $id = $data['id'] ?? null;
        unset($data['id']);

        $link = $id
            ? tap(WorkItemDeliveryLink::findOrFail($id))->update($data)
            : WorkItemDeliveryLink::updateOrCreate(
                [
                    'work_item_id' => $data['work_item_id'],
                    'type' => $data['type'],
                    'ref' => $data['ref'],
                ],
                $data,
            );

        if ($link->type === 'branch') {
            $this->clearResolvedBranchEvents($link);
        }

        return Response::structured([
            'id' => $link->id,
            'work_item_id' => $link->work_item_id,
            'type' => $link->type,
            'ref' => $link->ref,
            'url' => $link->url,
            'created' => $link->wasRecentlyCreated,
        ]);
    }

    /**
     * Once a branch is bound, the GitHub events that failed to attribute on
     * it leave the Evidence exception list — but only when the branch now
     * resolves to exactly one work item. A branch bound to several is still
     * ambiguous and unattributable, so its exceptions must stay visible.
     * (Events bound by trailer only fall to the retention prune instead.)
     */
    private function clearResolvedBranchEvents(WorkItemDeliveryLink $link): void
    {
        $githubRepo = $link->workItem?->project?->github_repo;

        if ($githubRepo === null) {
            return;
        }

        $distinctWorkItems = WorkItemDeliveryLink::query()
            ->where('type', 'branch')
            ->where('ref', $link->ref)
            ->whereHas('workItem.project', fn ($query) => $query->where('github_repo', $githubRepo))
            ->distinct()
            ->count('work_item_id');

        if ($distinctWorkItems === 1) {
            UnattributedGithubEvent::clearForBranch($githubRepo, $link->ref);
        }
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->description('Existing delivery link ULID. Omit to create.'),
            'work_item_id' => $schema->string()->description('Work item ULID')->required(),
            'type' => $schema->string()->description('Delivery link type')->enum(WorkItemDeliveryLink::TYPES)->required(),
            'ref' => $schema->string()->description('Commit SHA, pull request number/ref, branch name, or — for an evidence link — the pull request ref the gallery belongs to')->required(),
            'url' => $schema->string()->description('Optional URL to the commit, pull request, branch, or evidence gallery'),
            'description' => $schema->string()->description('Optional delivery evidence notes'),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->required(),
            'work_item_id' => $schema->string()->required(),
            'type' => $schema->string()->required(),
            'ref' => $schema->string()->required(),
            'url' => $schema->string(),
            'created' => $schema->boolean()->required(),
        ];
    }
}
