<?php

namespace App\Mcp\Tools\Capabilities;

use App\Growth\Alignment\AlignmentText;
use App\Models\Requirement;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('List capabilities for a project with optional layer, type, priority, and text filters. For relationships across entity types (which design elements, test cases, or work items derive from a capability), use `trace-query` with the capability id.')]
class ListCapabilities extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'project_id' => 'required|string|owned_project',
            'layer' => 'nullable|in:stakeholder,system,software',
            'type' => 'nullable|in:functional,performance,usability,interface,design_constraint,process,non_functional',
            'priority' => 'nullable|in:high,medium,low',
            'q' => 'nullable|string|max:255',
            'limit' => 'nullable|integer|min:1|max:200',
            'offset' => 'nullable|integer|min:0',
        ]);

        $limit = $data['limit'] ?? 50;
        $offset = $data['offset'] ?? 0;
        $query = Requirement::query()->where('project_id', $data['project_id']);

        if (isset($data['layer'])) {
            $query->where('doc', AlignmentText::layerToDoc($data['layer']));
        }
        foreach (['type', 'priority'] as $field) {
            if (isset($data[$field])) {
                $query->where($field, $data[$field]);
            }
        }
        if (isset($data['q'])) {
            $query->where('text', 'like', '%'.$data['q'].'%');
        }

        $total = (clone $query)->count();
        $rows = $query->orderBy('created_at')->limit($limit)->offset($offset)->get();

        return Response::structured([
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset,
            'results' => $rows->map(fn ($capability) => [
                'id' => $capability->id,
                'layer' => AlignmentText::docToLayer($capability->doc),
                'type' => $capability->type,
                'priority' => $capability->priority,
                'text' => $capability->text,
                'acceptance_checks' => $capability->acceptance_criteria ?? [],
                'source' => $capability->source,
                'parent_id' => $capability->parent_id,
                'created_at' => $capability->created_at?->toIso8601String(),
            ])->all(),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'project_id' => $schema->string()->description('Project ULID')->required(),
            'layer' => $schema->string()->description('Filter by capability layer')->enum(['stakeholder', 'system', 'software']),
            'type' => $schema->string()->description('Filter by capability type')->enum(['functional', 'performance', 'usability', 'interface', 'design_constraint', 'process', 'non_functional']),
            'priority' => $schema->string()->description('Filter by priority')->enum(['high', 'medium', 'low']),
            'q' => $schema->string()->description('Substring match on capability text'),
            'limit' => $schema->integer()->description('Page size, default 50'),
            'offset' => $schema->integer()->description('Pagination offset, default 0'),
        ];
    }
}
