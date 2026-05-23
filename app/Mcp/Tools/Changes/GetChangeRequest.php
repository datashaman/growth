<?php

namespace App\Mcp\Tools\Changes;

use App\Models\ChangeApprovalEvent;
use App\Models\ChangeImpact;
use App\Models\ChangeRequest;
use App\Support\ArtifactLink;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Model;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('Fetch one change request by ULID or per-project reference, including full editable fields, decision state, impacted artifacts, review linkage, approval history, and delivery links.')]
class GetChangeRequest extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'id' => 'nullable|string',
            'project_id' => 'required_without:id|nullable|string|owned_project',
            'reference' => 'required_without:id|nullable|string|max:64',
        ]);

        $change = isset($data['id'])
            ? $this->findById($data['id'])
            : $this->findByReference($data['project_id'], $data['reference']);

        if ($change === null) {
            return new ResponseFactory(Response::error('No change request matching that id or reference exists in the active workspace.'));
        }

        return Response::structured([
            'id' => $change->id,
            'project_id' => $change->project_id,
            'number' => $change->number,
            'reference' => $change->reference(),
            'title' => $change->title,
            'description' => $change->description,
            'rationale' => $change->rationale,
            'category' => $change->category,
            'status' => $change->status,
            'priority' => $change->priority,
            'decision' => $change->decision,
            'decision_rationale' => $change->decision_rationale,
            'decided_at' => $change->decided_at?->toIso8601String(),
            'requester_role' => $change->requesterRole ? [
                'id' => $change->requesterRole->id,
                'name' => $change->requesterRole->name,
            ] : null,
            'review' => $change->review ? [
                'id' => $change->review->id,
                'title' => $change->review->title,
                'type' => $change->review->type,
                'status' => $change->review->status,
                'decision' => $change->review->decision,
            ] : null,
            'impacts' => $change->impacts
                ->map(fn (ChangeImpact $impact): array => [
                    'id' => $impact->id,
                    'impactable_type' => $impact->impactable_type,
                    'impactable_id' => $impact->impactable_id,
                    'artifact' => $this->artifactSummary($impact->impactable, $impact->impactable_type),
                    'impact_kind' => $impact->impact_kind,
                    'description' => $impact->description,
                ])
                ->all(),
            'approval_events' => $change->approvalEvents
                ->map(fn (ChangeApprovalEvent $event): array => [
                    'id' => $event->id,
                    'from_status' => $event->from_status,
                    'to_status' => $event->to_status,
                    'from_decision' => $event->from_decision,
                    'to_decision' => $event->to_decision,
                    'rationale' => $event->rationale,
                    'recorded_by_user_id' => $event->recorded_by_user_id,
                    'recorded_by' => $event->recordedBy?->name,
                    'recorded_at' => $event->recorded_at?->toIso8601String(),
                ])
                ->all(),
            'delivery_links' => $change->deliveryLinks
                ->map(fn ($link): array => [
                    'id' => $link->id,
                    'type' => $link->type,
                    'ref' => $link->ref,
                    'url' => $link->url,
                    'description' => $link->description,
                ])
                ->all(),
            'change_impact_brief' => "growth://change-requests/{$change->id}/change-impact-brief",
            'created_at' => $change->created_at?->toIso8601String(),
            'updated_at' => $change->updated_at?->toIso8601String(),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->description('Change request ULID. If omitted, provide project_id and reference.'),
            'project_id' => $schema->string()->description('Project ULID required when resolving by reference.'),
            'reference' => $schema->string()->description('Per-project change request reference, e.g. CR-3 or 3.'),
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
            'description' => $schema->string(),
            'rationale' => $schema->string(),
            'category' => $schema->string()->required(),
            'status' => $schema->string()->required(),
            'priority' => $schema->string()->required(),
            'decision' => $schema->string(),
            'decision_rationale' => $schema->string(),
            'decided_at' => $schema->string(),
            'requester_role' => $schema->object(),
            'review' => $schema->object(),
            'impacts' => $schema->array()->required(),
            'approval_events' => $schema->array()->required(),
            'delivery_links' => $schema->array()->required(),
            'change_impact_brief' => $schema->string()->required(),
            'created_at' => $schema->string()->required(),
            'updated_at' => $schema->string()->required(),
        ];
    }

    private function findById(string $id): ?ChangeRequest
    {
        return ChangeRequest::query()
            ->with($this->relations())
            ->whereKey($id)
            ->first();
    }

    private function findByReference(string $projectId, string $reference): ?ChangeRequest
    {
        $number = $this->parseReference($reference);

        if ($number === null) {
            return null;
        }

        return ChangeRequest::query()
            ->with($this->relations())
            ->where('project_id', $projectId)
            ->where('number', $number)
            ->first();
    }

    /**
     * @return list<string>
     */
    private function relations(): array
    {
        return [
            'requesterRole:id,name',
            'review:id,title,type,status,decision',
            'impacts.impactable',
            'approvalEvents.recordedBy:id,name',
            'deliveryLinks:id,change_request_id,type,ref,url,description',
        ];
    }

    private function parseReference(string $reference): ?int
    {
        if (preg_match('/^(?:CR-)?0*(\d+)$/i', trim($reference), $matches) !== 1) {
            return null;
        }

        return (int) $matches[1];
    }

    /**
     * @return array{type:string,id:string,label:string|null,reference:string|null}|null
     */
    private function artifactSummary(?Model $artifact, string $type): ?array
    {
        if ($artifact === null) {
            return null;
        }

        return [
            'type' => $type,
            'id' => (string) $artifact->getKey(),
            'label' => ArtifactLink::label($artifact),
            'reference' => method_exists($artifact, 'reference') ? $artifact->reference() : null,
        ];
    }
}
