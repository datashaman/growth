<?php

namespace App\Mcp\Tools\Changes;

use App\Models\ChangeRequest;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('Resolve a per-project change request reference (e.g. "CR-3") within a GitHub repository to its change request, so PRs carrying a CR reference can be attributed without a work item.')]
class ResolveChangeRequestByReference extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'github_repo' => ['required', 'string', 'max:255', 'regex:/^[A-Za-z0-9_.-]+\/[A-Za-z0-9_.-]+$/'],
            'reference' => ['required', 'string', 'max:64'],
        ]);

        $number = $this->parseReference($data['reference']);

        $changeRequest = $number === null
            ? null
            : ChangeRequest::query()
                ->where('number', $number)
                ->whereHas('project', fn ($query) => $query->where('github_repo', $data['github_repo']))
                ->first(['id', 'title', 'status']);

        if ($changeRequest === null) {
            return Response::structured([
                'found' => false,
                'github_repo' => $data['github_repo'],
                'reference' => $data['reference'],
                'change_request_id' => null,
                'change_request_title' => null,
                'change_request_status' => null,
            ]);
        }

        return Response::structured([
            'found' => true,
            'github_repo' => $data['github_repo'],
            'reference' => $data['reference'],
            'change_request_id' => $changeRequest->id,
            'change_request_title' => $changeRequest->title,
            'change_request_status' => $changeRequest->status,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'github_repo' => $schema->string()->description('GitHub repository in owner/repo form')->required(),
            'reference' => $schema->string()->description('Per-project change request reference, e.g. "CR-3" or "3"')->required(),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'found' => $schema->boolean()->required(),
            'github_repo' => $schema->string()->required(),
            'reference' => $schema->string()->required(),
            'change_request_id' => $schema->string(),
            'change_request_title' => $schema->string(),
            'change_request_status' => $schema->string(),
        ];
    }

    private function parseReference(string $reference): ?int
    {
        if (preg_match('/^(?:CR-)?0*(\d+)$/i', trim($reference), $matches) !== 1) {
            return null;
        }

        return (int) $matches[1];
    }
}
