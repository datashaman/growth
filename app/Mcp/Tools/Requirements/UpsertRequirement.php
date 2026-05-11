<?php

namespace App\Mcp\Tools\Requirements;

use App\Growth\Lint\RequirementLinter;
use App\Models\Requirement;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Create or update a requirement (capability quality StRS/SyRS/SRS). Returns inline lint findings against rule and rule.')]
class UpsertRequirement extends Tool
{
    public function __construct(private readonly RequirementLinter $linter) {}

    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'id' => 'nullable|string|owned_requirement',
            'project_id' => 'required|string|owned_project',
            'parent_id' => 'nullable|string|owned_requirement',
            'doc' => 'required|in:strs,syrs,srs',
            'type' => 'required|in:functional,performance,usability,interface,design_constraint,process,non_functional',
            'text' => 'required|string|min:5',
            'rationale' => 'nullable|string',
            'acceptance_criteria' => 'nullable|array',
            'acceptance_criteria.*' => 'string|min:3',
            'source' => 'nullable|string|max:255',
            'priority' => 'nullable|in:high,medium,low',
            'tags' => 'nullable|array',
        ]);

        $id = $data['id'] ?? null;
        unset($data['id']);

        $requirement = $id
            ? tap(Requirement::findOrFail($id))->update($data)
            : Requirement::create($data);

        return Response::structured([
            'id' => $requirement->id,
            'created' => $requirement->wasRecentlyCreated,
            'lint' => $this->linter->check($requirement->fresh()),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()
                ->description('Existing requirement ID. Omit to create new.'),
            'project_id' => $schema->string()
                ->description('Project ULID')
                ->required(),
            'parent_id' => $schema->string()
                ->description('Parent requirement ULID (for derived requirements)'),
            'doc' => $schema->string()
                ->description('Which capability doc this belongs to')
                ->enum(['strs', 'syrs', 'srs'])
                ->required(),
            'type' => $schema->string()
                ->description('Requirement type per rule')
                ->enum(['functional', 'performance', 'usability', 'interface', 'design_constraint', 'process', 'non_functional'])
                ->required(),
            'text' => $schema->string()
                ->description('The requirement statement (should contain shall/must/will)')
                ->required(),
            'rationale' => $schema->string()
                ->description('Why this requirement exists '),
            'acceptance_criteria' => $schema->array()
                ->description('Concrete pass/fail criteria used to verify acceptance of this requirement'),
            'source' => $schema->string()
                ->description('Originating stakeholder or document'),
            'priority' => $schema->string()
                ->description('Stakeholder priority')
                ->enum(['high', 'medium', 'low']),
            'tags' => $schema->array()
                ->description('Free-form tags'),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->required(),
            'created' => $schema->boolean()->required(),
            'lint' => $schema->array()
                ->description('capability quality findings; empty array means clean'),
        ];
    }
}
