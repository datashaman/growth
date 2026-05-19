<?php

namespace App\Mcp\Tools\Feedback;

use App\Models\ToolInvocation;
use App\Support\WorkspaceContext;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('List MCP tool invocations recorded for the active workspace. Filter by tool name or success state; useful for spotting frequently-failing or slow tools.')]
class ListToolInvocations extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'tool_name' => 'nullable|string|max:120',
            'agent_id' => 'nullable|string|owned_agent',
            'success' => 'nullable|boolean',
            'since' => 'nullable|date',
            'limit' => 'nullable|integer|min:1|max:500',
            'offset' => 'nullable|integer|min:0',
        ]);

        $workspaceId = app(WorkspaceContext::class)->requireId();
        $limit = $data['limit'] ?? 50;
        $offset = $data['offset'] ?? 0;

        $query = ToolInvocation::query()->where('workspace_id', $workspaceId);
        if (isset($data['tool_name'])) {
            $query->where('tool_name', $data['tool_name']);
        }
        if (isset($data['agent_id'])) {
            $query->where('agent_id', $data['agent_id']);
        }
        if (array_key_exists('success', $data)) {
            $query->where('success', $data['success']);
        }
        if (isset($data['since'])) {
            $query->where('started_at', '>=', $data['since']);
        }

        $total = (clone $query)->count();

        $rows = $query
            ->orderByDesc('started_at')
            ->limit($limit)
            ->offset($offset)
            ->get([
                'id', 'tool_name', 'acting_surface', 'acting_role_id', 'acting_role_name',
                'transport', 'success', 'error_class', 'error_message',
                'duration_ms', 'args_shape', 'started_at',
            ]);

        return Response::structured([
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset,
            'results' => $rows->map(fn (ToolInvocation $row) => [
                'id' => $row->id,
                'tool_name' => $row->tool_name,
                'acting_surface' => $row->acting_surface,
                'acting_role_id' => $row->acting_role_id,
                'acting_role_name' => $row->acting_role_name,
                'transport' => $row->transport,
                'success' => $row->success,
                'error_class' => $row->error_class,
                'error_message' => $row->error_message,
                'duration_ms' => $row->duration_ms,
                'args_shape' => $row->args_shape,
                'started_at' => $row->started_at?->toIso8601String(),
            ])->all(),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'tool_name' => $schema->string()->description('Filter to a single tool name, e.g. upsert-requirements.'),
            'agent_id' => $schema->string()->description('Filter to invocations attributed to a single agent (ULID). Reproduces the activity counts reported by summarize-agent-outcomes.'),
            'success' => $schema->boolean()->description('Filter to successful (true) or failed (false) invocations.'),
            'since' => $schema->string()->description('ISO 8601 timestamp; only return invocations on or after this time.'),
            'limit' => $schema->integer()->description('Page size, max 500. Default 50.'),
            'offset' => $schema->integer()->description('Pagination offset. Default 0.'),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'total' => $schema->integer()->required(),
            'limit' => $schema->integer()->required(),
            'offset' => $schema->integer()->required(),
            'results' => $schema->array()->required(),
        ];
    }
}
