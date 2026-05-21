<?php

namespace App\Mcp\Tools\Feedback;

use App\Models\ToolFeedback;
use App\Support\WorkspaceContext;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Builder;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('Search feedback already submitted about the Growth MCP tools. Resolved feedback is excluded by default so the active queue stays focused; pass status=resolved to read it, or include_resolved=true to search across every status. When calling this before `send-feedback` to avoid filing a duplicate, set include_resolved=true so an already-resolved equivalent is still caught. Filter by free-text query, category, tool name, or triage status.')]
class SearchFeedback extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'query' => 'nullable|string|max:200',
            'category' => 'nullable|string|in:'.implode(',', ToolFeedback::CATEGORIES),
            'tool_name' => 'nullable|string|max:120',
            'status' => 'nullable|string|in:'.implode(',', ToolFeedback::STATUSES),
            'include_resolved' => 'nullable|boolean',
            'limit' => 'nullable|integer|min:1|max:100',
            'offset' => 'nullable|integer|min:0',
        ]);

        $limit = $data['limit'] ?? 25;
        $offset = $data['offset'] ?? 0;

        $query = ToolFeedback::query()
            ->where('workspace_id', app(WorkspaceContext::class)->requireId());

        if (isset($data['query'])) {
            $term = '%'.$data['query'].'%';
            $query->where(function (Builder $builder) use ($term): void {
                $builder->where('summary', 'like', $term)
                    ->orWhere('body', 'like', $term);
            });
        }
        if (isset($data['category'])) {
            $query->where('category', $data['category']);
        }
        if (isset($data['tool_name'])) {
            $query->where('tool_name', $data['tool_name']);
        }
        if (isset($data['status'])) {
            $query->where('status', $data['status']);
        } elseif (! ($data['include_resolved'] ?? false)) {
            // Resolved feedback is hidden by default so the active queue stays
            // focused; an explicit status filter or include_resolved reveals it.
            $query->where('status', '!=', 'resolved');
        }

        $total = (clone $query)->count();

        $rows = $query
            ->orderByDesc('created_at')
            ->limit($limit)
            ->offset($offset)
            ->get(['id', 'category', 'status', 'tool_name', 'summary', 'created_at']);

        return Response::structured([
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset,
            'results' => $rows->map(fn (ToolFeedback $row) => [
                'id' => $row->id,
                'category' => $row->category,
                'status' => $row->status,
                'tool_name' => $row->tool_name,
                'summary' => $row->summary,
                'created_at' => $row->created_at?->toIso8601String(),
            ])->all(),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()->description('Free-text term matched against feedback summary and body.'),
            'category' => $schema->string()->description('Filter to a single category.')->enum(ToolFeedback::CATEGORIES),
            'tool_name' => $schema->string()->description('Filter to feedback about one MCP tool, e.g. upsert-requirements.'),
            'status' => $schema->string()->description('Filter to a single triage status. Overrides include_resolved.')->enum(ToolFeedback::STATUSES),
            'include_resolved' => $schema->boolean()->description('Include resolved feedback in the results. Defaults to false (resolved is hidden). Ignored when an explicit status filter is given. Set true when checking for duplicates before send-feedback.'),
            'limit' => $schema->integer()->description('Page size, max 100. Default 25.'),
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
