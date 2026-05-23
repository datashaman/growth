<?php

namespace App\Mcp\Tools\Plan;

use App\Models\Requirement;
use App\Models\Role;
use App\Models\WorkItem;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('Fetch one work item by ULID or per-project reference, including full editable fields and role, RACI, requirement, milestone, dependency, and delivery-link context.')]
class GetWorkItem extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'id' => 'nullable|string',
            'project_id' => 'required_without:id|nullable|string|owned_project',
            'reference' => 'required_without:id|nullable|string|max:64',
        ]);

        $workItem = isset($data['id'])
            ? $this->findById($data['id'])
            : $this->findByReference($data['project_id'], $data['reference']);

        if ($workItem === null) {
            return new ResponseFactory(Response::error('No work item matching that id or reference exists in the active workspace.'));
        }

        return Response::structured([
            'id' => $workItem->id,
            'project_id' => $workItem->project_id,
            'number' => $workItem->number,
            'reference' => $workItem->reference(),
            'kind' => $workItem->kind,
            'name' => $workItem->name,
            'description' => $workItem->description,
            'status' => $workItem->status,
            'needs_mockups' => $workItem->needs_mockups,
            'parent' => $this->workItemSummary($workItem->parent),
            'children' => $workItem->children->map(fn (WorkItem $child): array => $this->workItemSummary($child))->all(),
            'responsible_role' => $this->roleSummary($workItem->responsibleRole),
            'raci' => $workItem->raciRoles
                ->map(fn (Role $role): array => [
                    'role_id' => $role->id,
                    'role' => $role->name,
                    'raci' => $role->pivot->raci,
                ])
                ->values()
                ->all(),
            'requirements' => $workItem->requirements
                ->map(fn (Requirement $requirement): array => [
                    'id' => $requirement->id,
                    'reference' => $requirement->reference(),
                    'doc' => $requirement->doc,
                    'type' => $requirement->type,
                    'text' => $requirement->text,
                    'priority' => $requirement->priority,
                ])
                ->all(),
            'milestones' => $workItem->milestones
                ->map(fn ($milestone): array => [
                    'id' => $milestone->id,
                    'name' => $milestone->name,
                    'status' => $milestone->status,
                ])
                ->all(),
            'dependencies' => $workItem->dependencies->map(fn (WorkItem $item): array => $this->workItemSummary($item))->all(),
            'dependents' => $workItem->dependents->map(fn (WorkItem $item): array => $this->workItemSummary($item))->all(),
            'delivery_links' => $workItem->deliveryLinks
                ->map(fn ($link): array => [
                    'id' => $link->id,
                    'type' => $link->type,
                    'ref' => $link->ref,
                    'url' => $link->url,
                    'description' => $link->description,
                ])
                ->all(),
            'mockups' => $workItem->mockups
                ->map(fn ($mockup): array => [
                    'id' => $mockup->id,
                    'name' => $mockup->name,
                ])
                ->all(),
            'implementation_brief' => "growth://work-items/{$workItem->id}/implementation-brief",
            'created_at' => $workItem->created_at?->toIso8601String(),
            'updated_at' => $workItem->updated_at?->toIso8601String(),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->description('Work item ULID. If omitted, provide project_id and reference.'),
            'project_id' => $schema->string()->description('Project ULID required when resolving by reference.'),
            'reference' => $schema->string()->description('Per-project work item reference, e.g. WI-42 or 42.'),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->required(),
            'project_id' => $schema->string()->required(),
            'number' => $schema->integer()->required(),
            'reference' => $schema->string()->required(),
            'kind' => $schema->string()->required(),
            'name' => $schema->string()->required(),
            'description' => $schema->string(),
            'status' => $schema->string()->required(),
            'needs_mockups' => $schema->boolean()->required(),
            'parent' => $schema->object(),
            'children' => $schema->array()->required(),
            'responsible_role' => $schema->object(),
            'raci' => $schema->array()->required(),
            'requirements' => $schema->array()->required(),
            'milestones' => $schema->array()->required(),
            'dependencies' => $schema->array()->required(),
            'dependents' => $schema->array()->required(),
            'delivery_links' => $schema->array()->required(),
            'mockups' => $schema->array()->required(),
            'implementation_brief' => $schema->string()->required(),
            'created_at' => $schema->string()->required(),
            'updated_at' => $schema->string()->required(),
        ];
    }

    private function findById(string $id): ?WorkItem
    {
        return WorkItem::query()
            ->with($this->relations())
            ->whereKey($id)
            ->first();
    }

    private function findByReference(string $projectId, string $reference): ?WorkItem
    {
        $number = $this->parseReference($reference);

        if ($number === null) {
            return null;
        }

        return WorkItem::query()
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
            'parent:id,project_id,number,kind,name,status',
            'children:id,project_id,parent_id,number,kind,name,status',
            'responsibleRole:id,name',
            'raciRoles:id,name',
            'requirements:id,project_id,number,doc,type,text,priority',
            'milestones:id,name,status',
            'dependencies:id,project_id,number,kind,name,status',
            'dependents:id,project_id,number,kind,name,status',
            'deliveryLinks:id,work_item_id,type,ref,url,description',
            'mockups:id,owner_type,owner_id,name',
        ];
    }

    private function parseReference(string $reference): ?int
    {
        if (preg_match('/^(?:WI-)?0*(\d+)$/i', trim($reference), $matches) !== 1) {
            return null;
        }

        return (int) $matches[1];
    }

    /**
     * @return array{id:string,reference:string,kind:string,name:string,status:string}|null
     */
    private function workItemSummary(?WorkItem $workItem): ?array
    {
        if ($workItem === null) {
            return null;
        }

        return [
            'id' => $workItem->id,
            'reference' => $workItem->reference(),
            'kind' => $workItem->kind,
            'name' => $workItem->name,
            'status' => $workItem->status,
        ];
    }

    /**
     * @return array{id:string,name:string}|null
     */
    private function roleSummary(?Role $role): ?array
    {
        return $role === null ? null : [
            'id' => $role->id,
            'name' => $role->name,
        ];
    }
}
