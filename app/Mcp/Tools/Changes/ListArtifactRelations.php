<?php

namespace App\Mcp\Tools\Changes;

use App\Models\ArtifactRelation;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('List explicit artifact relations such as supersedes and replaces for a project or artifact endpoint.')]
class ListArtifactRelations extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'project_id' => 'required|string|owned_project',
            'relation' => 'nullable|in:'.implode(',', ArtifactRelation::RELATIONS),
            'artifact_type' => 'nullable|string',
            'artifact_id' => 'nullable|string',
            'limit' => 'nullable|integer|min:1|max:200',
            'offset' => 'nullable|integer|min:0',
        ]);

        $limit = $data['limit'] ?? 50;
        $offset = $data['offset'] ?? 0;

        $query = ArtifactRelation::query()->where('project_id', $data['project_id']);

        if (isset($data['relation'])) {
            $query->where('relation', $data['relation']);
        }
        if (isset($data['artifact_type'], $data['artifact_id'])) {
            $query->where(fn ($q) => $q
                ->where(fn ($q) => $q
                    ->where('source_artifact_type', $data['artifact_type'])
                    ->where('source_artifact_id', $data['artifact_id']))
                ->orWhere(fn ($q) => $q
                    ->where('target_artifact_type', $data['artifact_type'])
                    ->where('target_artifact_id', $data['artifact_id'])));
        }

        $total = (clone $query)->count();

        $rows = $query->orderBy('relation')
            ->limit($limit)
            ->offset($offset)
            ->get();

        return Response::structured([
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset,
            'results' => $rows->map(fn (ArtifactRelation $relation): array => [
                'id' => $relation->id,
                'source_type' => $relation->source_artifact_type,
                'source_id' => $relation->source_artifact_id,
                'relation' => $relation->relation,
                'target_type' => $relation->target_artifact_type,
                'target_id' => $relation->target_artifact_id,
                'rationale' => $relation->rationale,
            ])->all(),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'project_id' => $schema->string()->description('Project ULID')->required(),
            'relation' => $schema->string()->description('Filter by relation')->enum(ArtifactRelation::RELATIONS),
            'artifact_type' => $schema->string()->description('Artifact endpoint type; use with artifact_id'),
            'artifact_id' => $schema->string()->description('Artifact endpoint ULID; use with artifact_type'),
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
