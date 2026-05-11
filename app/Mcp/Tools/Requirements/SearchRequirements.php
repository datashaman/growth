<?php

namespace App\Mcp\Tools\Requirements;

use App\Models\Requirement;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Search and filter requirements within a project. Supports filtering by doc, type, priority, free-text search on the requirement text, and pagination.')]
class SearchRequirements extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'project_id' => 'required|string|owned_project',
            'doc' => 'nullable|in:strs,syrs,srs',
            'type' => 'nullable|in:functional,performance,usability,interface,design_constraint,process,non_functional',
            'priority' => 'nullable|in:high,medium,low',
            'q' => 'nullable|string|max:255',
            'limit' => 'nullable|integer|min:1|max:200',
            'offset' => 'nullable|integer|min:0',
        ]);

        $limit = $data['limit'] ?? 50;
        $offset = $data['offset'] ?? 0;

        $query = Requirement::query()->where('project_id', $data['project_id']);

        foreach (['doc', 'type', 'priority'] as $field) {
            if (isset($data[$field])) {
                $query->where($field, $data[$field]);
            }
        }

        if (isset($data['q'])) {
            $query->where('text', 'like', '%'.$data['q'].'%');
        }

        $total = (clone $query)->count();

        $rows = $query->orderBy('created_at')
            ->limit($limit)
            ->offset($offset)
            ->get(['id', 'doc', 'type', 'priority', 'text', 'acceptance_criteria', 'source', 'parent_id', 'created_at']);

        return Response::structured([
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset,
            'results' => $rows->map(fn ($r) => [
                'id' => $r->id,
                'doc' => $r->doc,
                'type' => $r->type,
                'priority' => $r->priority,
                'text' => $r->text,
                'acceptance_criteria' => $r->acceptance_criteria ?? [],
                'source' => $r->source,
                'parent_id' => $r->parent_id,
                'created_at' => $r->created_at?->toIso8601String(),
            ])->all(),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'project_id' => $schema->string()
                ->description('Project ULID')
                ->required(),
            'doc' => $schema->string()
                ->description('Filter by capability doc')
                ->enum(['strs', 'syrs', 'srs']),
            'type' => $schema->string()
                ->description('Filter by requirement type')
                ->enum(['functional', 'performance', 'usability', 'interface', 'design_constraint', 'process', 'non_functional']),
            'priority' => $schema->string()
                ->description('Filter by stakeholder priority')
                ->enum(['high', 'medium', 'low']),
            'q' => $schema->string()
                ->description('Substring match on requirement text'),
            'limit' => $schema->integer()
                ->description('Page size (1-200, default 50)'),
            'offset' => $schema->integer()
                ->description('Offset for pagination (default 0)'),
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
