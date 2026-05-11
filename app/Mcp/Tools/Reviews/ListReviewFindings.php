<?php

namespace App\Mcp\Tools\Reviews;

use App\Models\ReviewFinding;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('List findings for a project or a specific review. Filterable by severity, status, owner role, and substring.')]
class ListReviewFindings extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'project_id' => 'required_without:review_id|string|owned_project',
            'review_id' => 'nullable|string|owned_review',
            'severity' => 'nullable|in:'.implode(',', ReviewFinding::SEVERITIES),
            'status' => 'nullable|in:'.implode(',', ReviewFinding::STATUSES),
            'owner_role_id' => 'nullable|string|owned_role',
            'q' => 'nullable|string|max:255',
            'limit' => 'nullable|integer|min:1|max:200',
            'offset' => 'nullable|integer|min:0',
        ]);

        $limit = $data['limit'] ?? 50;
        $offset = $data['offset'] ?? 0;

        $query = ReviewFinding::query()
            ->with(['review:id,title,type,status', 'ownerRole:id,name']);

        if (isset($data['review_id'])) {
            $query->where('review_id', $data['review_id']);
        } else {
            $query->where('project_id', $data['project_id']);
        }

        foreach (['severity', 'status', 'owner_role_id'] as $field) {
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
            ->orderByDesc('severity')
            ->orderBy('title')
            ->limit($limit)
            ->offset($offset)
            ->get();

        return Response::structured([
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset,
            'results' => $rows->map(fn (ReviewFinding $finding) => [
                'id' => $finding->id,
                'review_id' => $finding->review_id,
                'review' => $finding->review?->title,
                'title' => $finding->title,
                'severity' => $finding->severity,
                'status' => $finding->status,
                'owner_role_id' => $finding->owner_role_id,
                'owner_role' => $finding->ownerRole?->name,
                'reviewable_type' => $finding->reviewable_type,
                'reviewable_id' => $finding->reviewable_id,
                'due_at' => $finding->due_at?->toDateString(),
                'disposition' => $finding->disposition,
            ])->all(),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'project_id' => $schema->string()->description('Project ULID. Required unless review_id is provided.'),
            'review_id' => $schema->string()->description('Review ULID'),
            'severity' => $schema->string()->description('Filter by severity')->enum(ReviewFinding::SEVERITIES),
            'status' => $schema->string()->description('Filter by status')->enum(ReviewFinding::STATUSES),
            'owner_role_id' => $schema->string()->description('Filter by owner role ULID'),
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
