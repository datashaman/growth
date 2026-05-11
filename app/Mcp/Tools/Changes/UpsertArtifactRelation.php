<?php

namespace App\Mcp\Tools\Changes;

use App\Growth\Artifacts\ArtifactRegistry;
use App\Models\ArtifactRelation;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Create or update an explicit relation between two artifacts, such as supersedes or replaces, under project change control.')]
class UpsertArtifactRelation extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'id' => 'nullable|string|owned_artifact_relation',
            'project_id' => 'required|string|owned_project',
            'source_type' => 'required|string|in:'.implode(',', array_keys(ArtifactRegistry::types())),
            'source_id' => 'required|string',
            'relation' => 'required|in:'.implode(',', ArtifactRelation::RELATIONS),
            'target_type' => 'required|string|in:'.implode(',', array_keys(ArtifactRegistry::types())),
            'target_id' => 'required|string',
            'rationale' => 'nullable|string',
        ]);

        $this->validateArtifact($data['source_type'], $data['source_id'], $data['project_id'], 'source_id');
        $this->validateArtifact($data['target_type'], $data['target_id'], $data['project_id'], 'target_id');

        $attributes = [
            'project_id' => $data['project_id'],
            'source_artifact_type' => $data['source_type'],
            'source_artifact_id' => $data['source_id'],
            'relation' => $data['relation'],
            'target_artifact_type' => $data['target_type'],
            'target_artifact_id' => $data['target_id'],
            'rationale' => $data['rationale'] ?? null,
        ];

        $relation = isset($data['id'])
            ? tap(ArtifactRelation::findOrFail($data['id']))->update($attributes)
            : ArtifactRelation::updateOrCreate(
                collect($attributes)->except('rationale')->all(),
                $attributes,
            );

        return Response::structured([
            'id' => $relation->id,
            'project_id' => $relation->project_id,
            'source_type' => $relation->source_artifact_type,
            'source_id' => $relation->source_artifact_id,
            'relation' => $relation->relation,
            'target_type' => $relation->target_artifact_type,
            'target_id' => $relation->target_artifact_id,
            'created' => $relation->wasRecentlyCreated,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->description('Existing artifact relation ULID. Omit to create.'),
            'project_id' => $schema->string()->description('Project ULID')->required(),
            'source_type' => $schema->string()->description('Source artifact type')->enum(array_keys(ArtifactRegistry::types()))->required(),
            'source_id' => $schema->string()->description('Source artifact ULID')->required(),
            'relation' => $schema->string()->description('Relation from source to target')->enum(ArtifactRelation::RELATIONS)->required(),
            'target_type' => $schema->string()->description('Target artifact type')->enum(array_keys(ArtifactRegistry::types()))->required(),
            'target_id' => $schema->string()->description('Target artifact ULID')->required(),
            'rationale' => $schema->string()->description('Why this relation exists'),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->required(),
            'project_id' => $schema->string()->required(),
            'source_type' => $schema->string()->required(),
            'source_id' => $schema->string()->required(),
            'relation' => $schema->string()->required(),
            'target_type' => $schema->string()->required(),
            'target_id' => $schema->string()->required(),
            'created' => $schema->boolean()->required(),
        ];
    }

    private function validateArtifact(string $type, string $id, string $projectId, string $field): void
    {
        $artifact = ArtifactRegistry::validate($type, $id, $field, $field);
        if (ArtifactRegistry::projectId($artifact) !== $projectId) {
            throw ValidationException::withMessages([
                $field => 'Related artifacts must belong to the same project.',
            ]);
        }
    }
}
