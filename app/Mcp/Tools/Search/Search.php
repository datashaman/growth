<?php

namespace App\Mcp\Tools\Search;

use App\Growth\Search\SearchHit;
use App\Growth\Search\SearchService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('Search the active workspace for artifacts matching a free-text query, across projects, requirements, work items, risks, reviews, change requests, anomalies, milestones, releases, deployments, stakeholders, design elements/views, test plans/cases, and roles. Read-only. Use this instead of fanning out across the list-* tools when you only need to find something by name; then follow up with the specific list-* or detail tool for that hit.')]
class Search extends Tool
{
    public function handle(Request $request, SearchService $search): ResponseFactory
    {
        $data = $request->validate([
            'query' => 'required|string|min:2|max:255',
            'types' => 'nullable|array',
            'types.*' => 'string|in:'.implode(',', SearchService::types()),
            'limit' => 'nullable|integer|min:1|max:'.SearchService::MAX_LIMIT,
        ]);

        $hits = $search->search(
            $data['query'],
            $data['types'] ?? null,
            $data['limit'] ?? SearchService::DEFAULT_LIMIT,
        );

        return Response::structured([
            'query' => $data['query'],
            'total' => $hits->count(),
            'results' => $hits->map(fn (SearchHit $hit): array => $hit->toArray())->all(),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()->description('Free-text term; matched as a case-insensitive substring against each artifact type\'s text columns.')->required(),
            'types' => $schema->array()->description('Restrict the search to these entity types. Omit to search all of: '.implode(', ', SearchService::types()).'.'),
            'limit' => $schema->integer()->description('Maximum hits to return (1-'.SearchService::MAX_LIMIT.', default '.SearchService::DEFAULT_LIMIT.').'),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()->required(),
            'total' => $schema->integer()->required(),
            'results' => $schema->array()->required(),
        ];
    }
}
