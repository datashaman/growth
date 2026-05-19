<?php

namespace App\Mcp\Tools\Dashboard;

use App\Growth\Reporting\AgentOutcomeSummarizer;
use App\Support\WorkspaceContext;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('Summarise a per-agent track record for every agent in the active workspace — an "HR for agents" read-side view. For each agent, aggregates already-recorded events: activity (invocations, success rate, corrective actions), tool usage, error classes, durations, feedback received, and plan baselines authored. Pure read-side aggregation — records nothing. Activity, tool-usage, error and duration metrics are a trailing window (tool_invocations is pruned after 90 days); feedback and baseline counts are lifetime. The response carries the window boundary. corrective_actions is neutral activity the agent performed, not a reliability negative.')]
class SummarizeAgentOutcomes extends Tool
{
    public function __construct(private readonly AgentOutcomeSummarizer $summarizer) {}

    public function handle(Request $request): ResponseFactory
    {
        $workspaceId = app(WorkspaceContext::class)->requireId();

        return Response::structured($this->summarizer->summarize($workspaceId));
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'window' => $schema->object()->description('Boundary of the invocation-derived metrics: window_days, the `since` ISO 8601 cutoff, and a note on which counts are windowed vs lifetime.')->required(),
            'agents' => $schema->array()->description('One track record per agent in the active workspace; agents with no attributed work appear with all-zero metrics.')->required(),
        ];
    }
}
