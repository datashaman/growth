<?php

namespace App\Mcp\Tools\Plan;

use App\Models\WorkItem;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Resolve a per-project work item reference (e.g. "WI-42") within a GitHub repository to its work item, so events carrying a reference in the branch name can be attributed without a Growth-Work-Item trailer.')]
class ResolveWorkItemByReference extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'github_repo' => ['required', 'string', 'max:255', 'regex:/^[A-Za-z0-9_.-]+\/[A-Za-z0-9_.-]+$/'],
            'reference' => ['required', 'string', 'max:64'],
        ]);

        $number = $this->parseReference($data['reference']);

        $workItem = $number === null
            ? null
            : WorkItem::query()
                ->where('number', $number)
                ->whereHas('project', fn ($query) => $query->where('github_repo', $data['github_repo']))
                ->first(['id', 'name', 'status']);

        if ($workItem === null) {
            return Response::structured([
                'found' => false,
                'github_repo' => $data['github_repo'],
                'reference' => $data['reference'],
                'work_item_id' => null,
                'work_item_name' => null,
                'work_item_status' => null,
            ]);
        }

        return Response::structured([
            'found' => true,
            'github_repo' => $data['github_repo'],
            'reference' => $data['reference'],
            'work_item_id' => $workItem->id,
            'work_item_name' => $workItem->name,
            'work_item_status' => $workItem->status,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'github_repo' => $schema->string()->description('GitHub repository in owner/repo form')->required(),
            'reference' => $schema->string()->description('Per-project work item reference, e.g. "WI-42" or "42"')->required(),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'found' => $schema->boolean()->required(),
            'github_repo' => $schema->string()->required(),
            'reference' => $schema->string()->required(),
            'work_item_id' => $schema->string(),
            'work_item_name' => $schema->string(),
            'work_item_status' => $schema->string(),
        ];
    }

    /**
     * Extract the per-project number from a reference, accepting an optional
     * "WI-" prefix and leading zeros. Returns null if it is not a number.
     */
    private function parseReference(string $reference): ?int
    {
        if (preg_match('/^(?:WI-)?0*(\d+)$/i', trim($reference), $matches) !== 1) {
            return null;
        }

        return (int) $matches[1];
    }
}
