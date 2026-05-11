<?php

namespace App\Mcp\Tools;

use App\Growth\Alignment\AlignmentText;
use App\Growth\Lint\RequirementLinter;
use App\Models\Requirement;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Create or update a capability with concrete acceptance checks.')]
class UpsertCapability extends Tool
{
    public function __construct(private readonly RequirementLinter $linter) {}

    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'id' => 'nullable|string|owned_requirement',
            'project_id' => 'required|string|owned_project',
            'parent_id' => 'nullable|string|owned_requirement',
            'layer' => 'required|in:stakeholder,system,software',
            'type' => 'required|in:functional,performance,usability,interface,design_constraint,process,non_functional',
            'text' => 'required|string|min:5',
            'rationale' => 'nullable|string',
            'acceptance_checks' => 'nullable|array',
            'acceptance_checks.*' => 'string|min:3',
            'source' => 'nullable|string|max:255',
            'priority' => 'nullable|in:high,medium,low',
            'tags' => 'nullable|array',
        ]);

        $id = $data['id'] ?? null;
        unset($data['id']);

        $payload = [
            'project_id' => $data['project_id'],
            'parent_id' => $data['parent_id'] ?? null,
            'doc' => AlignmentText::layerToDoc($data['layer']),
            'type' => $data['type'],
            'text' => $data['text'],
            'rationale' => $data['rationale'] ?? null,
            'acceptance_criteria' => $data['acceptance_checks'] ?? null,
            'source' => $data['source'] ?? null,
            'priority' => $data['priority'] ?? 'medium',
            'tags' => $data['tags'] ?? null,
        ];

        $capability = $id
            ? tap(Requirement::findOrFail($id))->update($payload)
            : Requirement::create($payload);

        return Response::structured([
            'id' => $capability->id,
            'created' => $capability->wasRecentlyCreated,
            'layer' => AlignmentText::docToLayer($capability->doc),
            'findings' => AlignmentText::sanitizeArray($this->linter->check($capability->fresh())),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->description('Existing capability ULID. Omit to create new.'),
            'project_id' => $schema->string()->description('Project ULID')->required(),
            'parent_id' => $schema->string()->description('Parent capability ULID for derived capabilities'),
            'layer' => $schema->string()->description('Capability layer')->enum(['stakeholder', 'system', 'software'])->required(),
            'type' => $schema->string()->description('Capability type')->enum(['functional', 'performance', 'usability', 'interface', 'design_constraint', 'process', 'non_functional'])->required(),
            'text' => $schema->string()->description('Capability statement')->required(),
            'rationale' => $schema->string()->description('Why this capability matters'),
            'acceptance_checks' => $schema->array()->description('Concrete pass/fail checks for acceptance'),
            'source' => $schema->string()->description('Originating stakeholder, source, or decision'),
            'priority' => $schema->string()->description('Delivery priority')->enum(['high', 'medium', 'low']),
            'tags' => $schema->array()->description('Free-form tags'),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->required(),
            'created' => $schema->boolean()->required(),
            'layer' => $schema->string()->required(),
            'findings' => $schema->array()->description('Capability quality findings; empty array means clean'),
        ];
    }
}
