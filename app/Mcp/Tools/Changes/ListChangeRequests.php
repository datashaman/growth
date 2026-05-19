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
#[Description('List change requests for a project. Filterable by category, status, priority, requester role, review, and substring.')]
class ListChangeRequests extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'project_id' => 'required|string|owned_project',
            'category' => 'nullable|in:'.implode(',', ChangeRequest::CATEGORIES),
            'status' => 'nullable|in:'.implode(',', ChangeRequest::STATUSES),
            'priority' => 'nullable|in:'.implode(',', ChangeRequest::PRIORITIES),
            'requester_role_id' => 'nullable|string|owned_role',
            'review_id' => 'nullable|string|owned_review',
            'q' => 'nullable|string|max:255',
            'limit' => 'nullable|integer|min:1|max:200',
            'offset' => 'nullable|integer|min:0',
        ]);

        $limit = $data['limit'] ?? 50;
        $offset = $data['offset'] ?? 0;

        $query = ChangeRequest::query()
            ->where('project_id', $data['project_id'])
            ->with(['requesterRole:id,name', 'review:id,title,type,status'])
            ->withCount('impacts');

        foreach (['category', 'status', 'priority', 'requester_role_id', 'review_id'] as $field) {
            if (isset($data[$field])) {
                $query->where($field, $data[$field]);
            }
        }
        if (isset($data['q'])) {
            $query->where('title', 'like', '%'.$data['q'].'%');
        }

        $total = (clone $query)->count();

        $rows = $query
            ->orderBy('status')
            ->orderByDesc('priority')
            ->orderBy('title')
            ->limit($limit)
            ->offset($offset)
            ->get();

        return Response::structured([
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset,
            'results' => $rows->map(fn (ChangeRequest $change) => [
                'id' => $change->id,
                'number' => $change->number,
                'reference' => $change->reference(),
                'title' => $change->title,
                'category' => $change->category,
                'status' => $change->status,
                'priority' => $change->priority,
                'decision' => $change->decision,
                'requester_role_id' => $change->requester_role_id,
                'requester_role' => $change->requesterRole?->name,
                'review_id' => $change->review_id,
                'review' => $change->review?->title,
                'impacts' => $change->impacts_count,
            ])->all(),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'project_id' => $schema->string()->description('Project ULID')->required(),
            'category' => $schema->string()->description('Filter by category')->enum(ChangeRequest::CATEGORIES),
            'status' => $schema->string()->description('Filter by status')->enum(ChangeRequest::STATUSES),
            'priority' => $schema->string()->description('Filter by priority')->enum(ChangeRequest::PRIORITIES),
            'requester_role_id' => $schema->string()->description('Filter by requester role ULID'),
            'review_id' => $schema->string()->description('Filter by review ULID'),
            'q' => $schema->string()->description('Substring match on title'),
            'limit' => $schema->integer()->description('Page size (1-200, default 50)'),
            'offset' => $schema->integer()->description('Offset for pagination (default 0)'),
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
