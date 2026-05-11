<?php

namespace App\Mcp\Tools\Sources;

use App\Models\Source;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('List sources for a project. Always projects narrow columns (id, title, kind, uri, external_ref) — source bodies are excluded to keep payloads small. Use trace-query or a direct resource fetch to retrieve a source body.')]
class ListSources extends Tool
{
    private const KINDS = [
        'brief', 'rfp', 'interview', 'transcript', 'contract',
        'source', 'ticket', 'email', 'doc', 'prototype', 'other',
    ];

    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'project_id' => 'required|string|owned_project',
            'kind' => 'nullable|in:'.implode(',', self::KINDS),
            'q' => 'nullable|string|max:255',
            'limit' => 'nullable|integer|min:1|max:200',
            'offset' => 'nullable|integer|min:0',
        ]);

        $limit = $data['limit'] ?? 50;
        $offset = $data['offset'] ?? 0;

        $query = Source::query()->where('project_id', $data['project_id']);

        if (isset($data['kind'])) {
            $query->where('kind', $data['kind']);
        }
        if (isset($data['q'])) {
            $query->where('title', 'like', '%'.$data['q'].'%');
        }

        $total = (clone $query)->count();

        $rows = $query->orderBy('created_at')
            ->limit($limit)
            ->offset($offset)
            ->get(['id', 'title', 'kind', 'uri', 'external_ref', 'created_at']);

        return Response::structured([
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset,
            'results' => $rows->map(fn ($s) => [
                'id' => $s->id,
                'title' => $s->title,
                'kind' => $s->kind,
                'uri' => $s->uri,
                'external_ref' => $s->external_ref,
                'created_at' => $s->created_at?->toIso8601String(),
            ])->all(),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'project_id' => $schema->string()
                ->description('Project ULID')
                ->required(),
            'kind' => $schema->string()
                ->description('Filter by source kind')
                ->enum(self::KINDS),
            'q' => $schema->string()
                ->description('Substring match on title'),
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
