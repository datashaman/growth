<?php

namespace App\Mcp\Tools\Plan;

use App\Models\WorkItemDeliveryLink;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Resolve a GitHub branch to the work item bound to it via a branch delivery link, so events on commits without a Growth-Work-Item trailer can still be attributed.')]
class ResolveWorkItemByBranch extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'github_repo' => ['required', 'string', 'max:255', 'regex:/^[A-Za-z0-9_.-]+\/[A-Za-z0-9_.-]+$/'],
            'branch' => ['required', 'string', 'max:255'],
        ]);

        $link = WorkItemDeliveryLink::query()
            ->where('type', 'branch')
            ->where('ref', $data['branch'])
            ->whereHas('workItem.project', fn ($query) => $query->where('github_repo', $data['github_repo']))
            ->with('workItem:id,name,status')
            ->first();

        if ($link === null) {
            return Response::structured([
                'found' => false,
                'github_repo' => $data['github_repo'],
                'branch' => $data['branch'],
                'work_item_id' => null,
                'work_item_name' => null,
                'work_item_status' => null,
            ]);
        }

        return Response::structured([
            'found' => true,
            'github_repo' => $data['github_repo'],
            'branch' => $data['branch'],
            'work_item_id' => $link->workItem->id,
            'work_item_name' => $link->workItem->name,
            'work_item_status' => $link->workItem->status,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'github_repo' => $schema->string()->description('GitHub repository in owner/repo form')->required(),
            'branch' => $schema->string()->description('Branch name, e.g. the head ref of a pull request')->required(),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'found' => $schema->boolean()->required(),
            'github_repo' => $schema->string()->required(),
            'branch' => $schema->string()->required(),
            'work_item_id' => $schema->string(),
            'work_item_name' => $schema->string(),
            'work_item_status' => $schema->string(),
        ];
    }
}
