<?php

namespace App\Mcp\Tools\Plan;

use App\Models\UnattributedGithubEvent;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[IsDestructive(false)]
#[Description('Record a GitHub event that could not be attributed to a work item, so the gap is visible on the Evidence page instead of evaporating silently.')]
class RecordUnattributedEvent extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'github_repo' => ['required', 'string', 'max:255', 'regex:/^[A-Za-z0-9_.-]+\/[A-Za-z0-9_.-]+$/', 'owned_project_repo'],
            'event_type' => ['required', 'string', 'in:'.implode(',', UnattributedGithubEvent::EVENT_TYPES)],
            'branch' => ['nullable', 'string', 'max:255'],
            'commit_sha' => ['required', 'string', 'max:255'],
            'reason' => ['required', 'string', 'in:'.implode(',', UnattributedGithubEvent::REASONS)],
            'url' => ['nullable', 'url', 'max:2048'],
        ]);

        $event = UnattributedGithubEvent::updateOrCreate(
            [
                'github_repo' => $data['github_repo'],
                'commit_sha' => $data['commit_sha'],
            ],
            [
                'event_type' => $data['event_type'],
                'branch' => $data['branch'] ?? null,
                'reason' => $data['reason'],
                'url' => $data['url'] ?? null,
                'received_at' => now(),
            ],
        );

        UnattributedGithubEvent::pruneExpired();

        return Response::structured([
            'id' => $event->id,
            'github_repo' => $event->github_repo,
            'commit_sha' => $event->commit_sha,
            'recorded' => true,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'github_repo' => $schema->string()->description('GitHub repository in owner/repo form')->required(),
            'event_type' => $schema->string()->description('GitHub event type')->enum(UnattributedGithubEvent::EVENT_TYPES)->required(),
            'branch' => $schema->string()->description('Branch the event belongs to, when known'),
            'commit_sha' => $schema->string()->description('Commit SHA the event is about')->required(),
            'reason' => $schema->string()->description('Why attribution failed')->enum(UnattributedGithubEvent::REASONS)->required(),
            'url' => $schema->string()->description('Optional URL to the event on GitHub'),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->required(),
            'github_repo' => $schema->string()->required(),
            'commit_sha' => $schema->string()->required(),
            'recorded' => $schema->boolean()->required(),
        ];
    }
}
