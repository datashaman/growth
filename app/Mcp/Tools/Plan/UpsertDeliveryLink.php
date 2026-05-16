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

#[Description('Create or update an implementation evidence link from a work item to a commit, pull request, or branch.')]
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

        // Binding a branch resolves the attribution gap, so any GitHub
        // events that failed on this branch leave the Evidence exception
        // list. (Events on bound-by-trailer-only paths fall to the prune.)
        if ($link->type === 'branch') {
            $githubRepo = $link->workItem?->project?->github_repo;

            if ($githubRepo !== null) {
                UnattributedGithubEvent::clearForBranch($githubRepo, $link->ref);
            }
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

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->description('Existing delivery link ULID. Omit to create.'),
            'work_item_id' => $schema->string()->description('Work item ULID')->required(),
            'type' => $schema->string()->description('Delivery link type')->enum(WorkItemDeliveryLink::TYPES)->required(),
            'ref' => $schema->string()->description('Commit SHA, pull request number/ref, or branch name')->required(),
            'url' => $schema->string()->description('Optional URL to the commit, pull request, or branch'),
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
