<?php

namespace App\Mcp\Tools\Changes;

use App\Growth\Artifacts\ArtifactRegistry;
use App\Models\ChangeImpact;
use App\Models\ChangeRequest;
use App\Models\Review;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Create or update a project change request, including optional impacted artifacts for impact analysis and review linkage.')]
class UpsertChangeRequest extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'id' => 'nullable|string|owned_change_request',
            'project_id' => 'required|string|owned_project',
            'requester_role_id' => 'nullable|string|owned_role',
            'review_id' => 'nullable|string|owned_review',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'rationale' => 'nullable|string',
            'category' => 'required|in:'.implode(',', ChangeRequest::CATEGORIES),
            'status' => 'prohibited',
            'priority' => 'nullable|in:'.implode(',', ChangeRequest::PRIORITIES),
            'decision' => 'prohibited',
            'decision_rationale' => 'prohibited',
            'decided_at' => 'prohibited',
            'impacts' => 'nullable|array',
            'impacts.*.type' => 'required_with:impacts|string|in:'.implode(',', array_keys(ArtifactRegistry::types())),
            'impacts.*.id' => 'required_with:impacts|string',
            'impacts.*.impact_kind' => 'required_with:impacts|in:'.implode(',', ChangeImpact::KINDS),
            'impacts.*.description' => 'nullable|string',
        ], [
            'status.prohibited' => 'Change request status is not set here. Use the submit-, approve-, reject-, defer-, mark-change-request-implemented, and cancel-change-request tools to move status through validated transitions.',
            'decision.prohibited' => 'Change request decisions are recorded by the approve-, reject-, and defer-change-request tools, not set directly.',
            'decision_rationale.prohibited' => 'Pass decision rationale as the reason argument to the approve-, reject-, or defer-change-request tool.',
            'decided_at.prohibited' => 'The decision timestamp is set automatically by the approve-, reject-, and defer-change-request tools.',
        ]);

        if (isset($data['review_id'])) {
            $review = Review::findOrFail($data['review_id']);
            if ($review->project_id !== $data['project_id']) {
                throw ValidationException::withMessages([
                    'review_id' => 'Review must belong to the same project as the change request.',
                ]);
            }
        }

        $impacts = $data['impacts'] ?? null;
        unset($data['impacts']);

        $id = $data['id'] ?? null;
        unset($data['id']);

        $change = $id
            ? tap(ChangeRequest::findOrFail($id))->update($data)
            : DB::transaction(fn () => ChangeRequest::create($data));

        if ($impacts !== null) {
            $this->syncImpacts($change, $impacts);
        }

        return Response::structured([
            'id' => $change->id,
            'project_id' => $change->project_id,
            'number' => $change->number,
            'reference' => $change->reference(),
            'title' => $change->title,
            'category' => $change->category,
            'status' => $change->status,
            'priority' => $change->priority,
            'decision' => $change->decision,
            'impacts' => $change->impacts()->count(),
            'approval_events' => $change->approvalEvents()->count(),
            'created' => $change->wasRecentlyCreated,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->description('Existing change request ULID. Omit to create.'),
            'project_id' => $schema->string()->description('Project ULID')->required(),
            'requester_role_id' => $schema->string()->description('Role ULID requesting the change'),
            'review_id' => $schema->string()->description('Review ULID where this change was approved or raised'),
            'title' => $schema->string()->description('Change request title. Do not embed a "CR-NNN" prefix — a per-project reference is assigned automatically.')->required(),
            'description' => $schema->string()->description('Change description'),
            'rationale' => $schema->string()->description('Reason for the change'),
            'category' => $schema->string()->description('Change category')->enum(ChangeRequest::CATEGORIES)->required(),
            'priority' => $schema->string()->description('Change priority')->enum(ChangeRequest::PRIORITIES),
            'impacts' => $schema->array()
                ->description('Impacted artifacts. Each entry: {type, id, impact_kind, description?}.')
                ->items($schema->object(fn (JsonSchema $s) => [
                    'type' => $s->string()
                        ->description('Artifact type the change touches.')
                        ->enum(array_keys(ArtifactRegistry::types()))
                        ->required(),
                    'id' => $s->string()
                        ->description('Artifact ULID.')
                        ->required(),
                    'impact_kind' => $s->string()
                        ->description('How this change affects the artifact.')
                        ->enum(ChangeImpact::KINDS)
                        ->required(),
                    'description' => $s->string()->description('Free-form notes on the impact'),
                ])),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->required(),
            'project_id' => $schema->string()->required(),
            'number' => $schema->integer()->required(),
            'reference' => $schema->string()->required(),
            'title' => $schema->string()->required(),
            'category' => $schema->string()->required(),
            'status' => $schema->string()->required(),
            'priority' => $schema->string()->required(),
            'decision' => $schema->string(),
            'impacts' => $schema->integer()->required(),
            'approval_events' => $schema->integer()->required(),
            'created' => $schema->boolean()->required(),
        ];
    }

    private function syncImpacts(ChangeRequest $change, array $impacts): void
    {
        $rows = [];

        foreach ($impacts as $impact) {
            $artifact = ArtifactRegistry::validate($impact['type'], $impact['id']);
            if (ArtifactRegistry::projectId($artifact) !== $change->project_id) {
                throw ValidationException::withMessages([
                    'impacts' => 'Change impacts must belong to the same project as the change request.',
                ]);
            }

            $rows[] = [
                'impactable_type' => $impact['type'],
                'impactable_id' => $impact['id'],
                'impact_kind' => $impact['impact_kind'],
                'description' => $impact['description'] ?? null,
            ];
        }

        $change->impacts()->delete();
        foreach ($rows as $row) {
            $change->impacts()->create($row);
        }
    }
}
