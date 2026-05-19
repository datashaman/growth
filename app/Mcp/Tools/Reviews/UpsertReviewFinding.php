<?php

namespace App\Mcp\Tools\Reviews;

use App\Growth\Artifacts\ArtifactRegistry;
use App\Mcp\Tools\Reviews\Concerns\ValidatesReviewArtifacts;
use App\Models\Review;
use App\Models\ReviewFinding;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[IsDestructive(false)]
#[Description('Create or update a finding from a review. Findings may point at the reviewed artifact they concern. New findings start as `open`; status is not set here — it moves only through the disposition-finding, resolve-finding, accept-finding, close-finding, and reopen-finding transitions.')]
class UpsertReviewFinding extends Tool
{
    use ValidatesReviewArtifacts;

    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'id' => 'nullable|string|owned_review_finding',
            'review_id' => 'required|string|owned_review',
            'owner_role_id' => 'nullable|string|owned_role',
            'reviewable_type' => 'nullable|required_with:reviewable_id|string|in:'.implode(',', array_keys($this->reviewableTypes())),
            'reviewable_id' => 'nullable|required_with:reviewable_type|string',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'severity' => 'nullable|in:'.implode(',', ReviewFinding::SEVERITIES),
            'status' => 'prohibited',
            'due_at' => 'nullable|date',
            'disposition' => 'nullable|string',
        ], [
            'status.prohibited' => 'Review finding status is not set here. Use the disposition-finding, resolve-finding, accept-finding, close-finding, and reopen-finding tools to move status through validated transitions.',
        ]);

        $review = Review::findOrFail($data['review_id']);
        $data['project_id'] = $review->project_id;

        if (isset($data['reviewable_type'], $data['reviewable_id'])) {
            $artifact = $this->validateReviewable($data['reviewable_type'], $data['reviewable_id']);
            if (ArtifactRegistry::projectId($artifact) !== $review->project_id) {
                throw ValidationException::withMessages([
                    'reviewable_id' => 'Review finding artifacts must belong to the same project as the review.',
                ]);
            }
        }

        $id = $data['id'] ?? null;
        unset($data['id']);

        $finding = $id
            ? tap(ReviewFinding::findOrFail($id))->update($data)
            : ReviewFinding::create($data + ['status' => 'open']);

        return Response::structured([
            'id' => $finding->id,
            'review_id' => $finding->review_id,
            'project_id' => $finding->project_id,
            'title' => $finding->title,
            'severity' => $finding->severity,
            'status' => $finding->status,
            'created' => $finding->wasRecentlyCreated,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->description('Existing review finding ULID. Omit to create.'),
            'review_id' => $schema->string()->description('Review ULID')->required(),
            'owner_role_id' => $schema->string()->description('Role ULID accountable for disposition'),
            'reviewable_type' => $schema->string()->description('Artifact type the finding concerns')->enum(array_keys($this->reviewableTypes())),
            'reviewable_id' => $schema->string()->description('Artifact ULID the finding concerns'),
            'title' => $schema->string()->description('Finding title')->required(),
            'description' => $schema->string()->description('Finding detail'),
            'severity' => $schema->string()->description('Finding severity')->enum(ReviewFinding::SEVERITIES),
            'due_at' => $schema->string()->description('Due date for disposition/resolution'),
            'disposition' => $schema->string()->description('Disposition or closure rationale'),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->required(),
            'review_id' => $schema->string()->required(),
            'project_id' => $schema->string()->required(),
            'title' => $schema->string()->required(),
            'severity' => $schema->string()->required(),
            'status' => $schema->string()->required(),
            'created' => $schema->boolean()->required(),
        ];
    }
}
