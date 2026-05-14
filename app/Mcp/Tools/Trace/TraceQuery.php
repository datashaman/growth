<?php

namespace App\Mcp\Tools\Trace;

use App\Growth\Trace\TraceResolver;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Walk the traceability graph from any artifact (requirement, concern, design view/element, test plan/case/run, anomaly, stakeholder). Returns a flat `nodes` + `edges` graph up to a configurable depth — preferred over chained `list-*` calls when you need relationships across entity types.

Common queries:
- `{id: <capability-id>}` — find every design element, test case, and work item derived from a capability (default depth 3, direction both).
- `{id: <concern-id>, direction: down}` — find every architecture view and capability that addresses a concern.
- `{id: <work-item-id>, direction: up}` — find the capabilities, milestones, and roles that justify a work item.
- `{id: <anomaly-id>, depth: 2}` — find the failing test run and its plan/case from an anomaly.
- `{id: <test-plan-id>, direction: down}` — find every case and run produced under a verification plan.

Use `list-*` when you only need one entity type; use `trace-query` when you need a relationship.')]
class TraceQuery extends Tool
{
    public function __construct(private readonly TraceResolver $resolver) {}

    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'id' => 'required|string',
            'depth' => 'nullable|integer|min:1|max:6',
            'direction' => 'nullable|in:up,down,both',
        ]);

        $resolved = $this->resolver->resolve($data['id']);

        if ($resolved === null) {
            return Response::structured([
                'error' => "No artifact found with id [{$data['id']}]",
                'nodes' => [],
                'edges' => [],
            ]);
        }

        $graph = $this->resolver->walk(
            $resolved['model'],
            $resolved['type'],
            $data['depth'] ?? 3,
            $data['direction'] ?? 'both',
        );

        return Response::structured([
            'starting' => [
                'id' => $resolved['model']->getKey(),
                'type' => $resolved['type'],
            ],
            'nodes' => $graph['nodes'],
            'edges' => $graph['edges'],
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()
                ->description('Starting artifact ULID (any type)')
                ->required(),
            'depth' => $schema->integer()
                ->description('Max hops to walk (1-6, default 3)'),
            'direction' => $schema->string()
                ->description('up = parents/sources, down = derived/dependents, both (default)')
                ->enum(['up', 'down', 'both']),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'starting' => $schema->object()
                ->description('The resolved starting artifact (type + id)'),
            'nodes' => $schema->array()
                ->description('All visited artifacts')
                ->required(),
            'edges' => $schema->array()
                ->description('Edges with from/to/label/direction')
                ->required(),
        ];
    }
}
