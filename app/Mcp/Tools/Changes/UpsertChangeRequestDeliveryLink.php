<?php

namespace App\Mcp\Tools\Changes;

use App\Models\ChangeRequestDeliveryLink;
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
#[Description('Create or update implementation evidence linking a change request to a commit, pull request, or branch. Used by growth-sync for CR-only work that has no work item.')]
class UpsertChangeRequestDeliveryLink extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'id' => 'nullable|string|owned_change_request_delivery_link',
            'change_request_id' => 'required|string|owned_change_request',
            'type' => 'required|in:'.implode(',', ChangeRequestDeliveryLink::TYPES),
            'ref' => 'required|string|max:255',
            'url' => 'nullable|url|max:2048',
            'description' => 'nullable|string',
        ]);

        $id = $data['id'] ?? null;
        unset($data['id']);

        $data['ref'] = ChangeRequestDeliveryLink::canonicalRef($data['type'], $data['ref']);

        $link = $id
            ? tap(ChangeRequestDeliveryLink::findOrFail($id))->update($data)
            : ChangeRequestDeliveryLink::updateOrCreate(
                [
                    'change_request_id' => $data['change_request_id'],
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
            'change_request_id' => $link->change_request_id,
            'type' => $link->type,
            'ref' => $link->ref,
            'url' => $link->url,
            'created' => $link->wasRecentlyCreated,
        ]);
    }

    private function clearResolvedBranchEvents(ChangeRequestDeliveryLink $link): void
    {
        $githubRepo = $link->changeRequest?->project?->github_repo;

        if ($githubRepo === null) {
            return;
        }

        $distinctChangeRequests = ChangeRequestDeliveryLink::query()
            ->where('type', 'branch')
            ->where('ref', $link->ref)
            ->whereHas('changeRequest.project', fn ($query) => $query->where('github_repo', $githubRepo))
            ->distinct()
            ->count('change_request_id');
        $distinctWorkItems = WorkItemDeliveryLink::query()
            ->where('type', 'branch')
            ->where('ref', $link->ref)
            ->whereHas('workItem.project', fn ($query) => $query->where('github_repo', $githubRepo))
            ->distinct()
            ->count('work_item_id');

        if ($distinctChangeRequests === 1 && $distinctWorkItems < 2) {
            UnattributedGithubEvent::clearForBranch($githubRepo, $link->ref);
        }
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->description('Existing change-request delivery link ULID. Omit to create.'),
            'change_request_id' => $schema->string()->description('Change request ULID')->required(),
            'type' => $schema->string()->description('Delivery link type')->enum(ChangeRequestDeliveryLink::TYPES)->required(),
            'ref' => $schema->string()->description('Commit SHA, branch name, or pull request ref — for a pull request use the canonical `#<number>` form (a bare number, `PR-<n>`, or a `.../pull/<n>` URL are accepted and normalised).')->required(),
            'url' => $schema->string()->description('Optional URL to the commit, pull request, or branch'),
            'description' => $schema->string()->description('Optional delivery evidence notes'),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->required(),
            'change_request_id' => $schema->string()->required(),
            'type' => $schema->string()->required(),
            'ref' => $schema->string()->required(),
            'url' => $schema->string(),
            'created' => $schema->boolean()->required(),
        ];
    }
}
