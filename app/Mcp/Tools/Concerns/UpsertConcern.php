<?php

namespace App\Mcp\Tools\Concerns;

use App\Models\Concern;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Create or update a design concern (architecture coverage rules). A concern is an area of interest raised by a stakeholder; concerns are framed by design viewpoints.')]
class UpsertConcern extends Tool
{
    private const VALID_VIEWPOINTS = [
        'context', 'composition', 'logical', 'dependency', 'information',
        'patterns', 'interface', 'structure', 'interaction', 'state_dynamics',
        'algorithm', 'resource',
    ];

    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'id' => 'nullable|string|owned_concern',
            'project_id' => 'required|string|owned_project',
            'raised_by_stakeholder_id' => 'nullable|string|owned_stakeholder',
            'text' => 'required|string|min:3',
            'viewpoint_hints' => 'nullable|array',
            'viewpoint_hints.*' => 'string|in:'.implode(',', self::VALID_VIEWPOINTS),
        ]);

        $id = $data['id'] ?? null;
        unset($data['id']);

        $concern = $id
            ? tap(Concern::findOrFail($id))->update($data)
            : Concern::create($data);

        return Response::structured([
            'id' => $concern->id,
            'text' => $concern->text,
            'viewpoint_hints' => $concern->viewpoint_hints ?? [],
            'created' => $concern->wasRecentlyCreated,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()
                ->description('Existing concern ID. Omit to create new.'),
            'project_id' => $schema->string()
                ->description('Project ULID')
                ->required(),
            'raised_by_stakeholder_id' => $schema->string()
                ->description('Optional stakeholder ULID who raised this concern'),
            'text' => $schema->string()
                ->description('The concern statement (e.g. "How does the system tolerate network partitions?")')
                ->required(),
            'viewpoint_hints' => $schema->array()
                ->description('Suggested architecture coverage viewpoints that should frame this concern. Values: '.implode(', ', self::VALID_VIEWPOINTS)),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->required(),
            'text' => $schema->string()->required(),
            'viewpoint_hints' => $schema->array(),
            'created' => $schema->boolean()->required(),
        ];
    }
}
