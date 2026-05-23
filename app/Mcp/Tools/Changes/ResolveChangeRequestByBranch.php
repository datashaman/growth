<?php

namespace App\Mcp\Tools\Changes;

use App\Models\ChangeRequestDeliveryLink;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('Resolve a GitHub branch to the change request bound to it via a branch delivery link, so CR-only PRs without a work item can still be attributed.')]
class ResolveChangeRequestByBranch extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'github_repo' => ['required', 'string', 'max:255', 'regex:/^[A-Za-z0-9_.-]+\/[A-Za-z0-9_.-]+$/'],
            'branch' => ['required', 'string', 'max:255'],
        ]);

        $changeRequests = ChangeRequestDeliveryLink::query()
            ->where('type', 'branch')
            ->where('ref', $data['branch'])
            ->whereHas('changeRequest.project', fn ($query) => $query->where('github_repo', $data['github_repo']))
            ->with('changeRequest:id,title,status')
            ->get()
            ->pluck('changeRequest')
            ->filter()
            ->unique('id')
            ->values();

        if ($changeRequests->count() !== 1) {
            return Response::structured([
                'found' => false,
                'ambiguous' => $changeRequests->count() > 1,
                'github_repo' => $data['github_repo'],
                'branch' => $data['branch'],
                'change_request_id' => null,
                'change_request_title' => null,
                'change_request_status' => null,
            ]);
        }

        $changeRequest = $changeRequests->first();

        return Response::structured([
            'found' => true,
            'ambiguous' => false,
            'github_repo' => $data['github_repo'],
            'branch' => $data['branch'],
            'change_request_id' => $changeRequest->id,
            'change_request_title' => $changeRequest->title,
            'change_request_status' => $changeRequest->status,
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
            'ambiguous' => $schema->boolean()->required(),
            'github_repo' => $schema->string()->required(),
            'branch' => $schema->string()->required(),
            'change_request_id' => $schema->string(),
            'change_request_title' => $schema->string(),
            'change_request_status' => $schema->string(),
        ];
    }
}
