<?php

namespace App\Mcp\Tools\Requirements;

use App\Growth\Reviews\RequirementReviewCoverage;
use App\Models\Requirement;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('List requirement review coverage for a project: review counts, accepted review evidence, open findings, and latest review state.')]
class ListRequirementReviewStatus extends Tool
{
    public function __construct(private readonly RequirementReviewCoverage $coverage) {}

    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'project_id' => 'required|string|owned_project',
            'only_gaps' => 'nullable|boolean',
            'limit' => 'nullable|integer|min:1|max:200',
            'offset' => 'nullable|integer|min:0',
        ]);

        $limit = $data['limit'] ?? 50;
        $offset = $data['offset'] ?? 0;

        $query = Requirement::query()
            ->where('project_id', $data['project_id'])
            ->orderBy('doc')
            ->orderBy('type')
            ->orderBy('id');

        $requirements = $query->get();

        $rows = $requirements
            ->map(fn (Requirement $requirement): array => [
                'id' => $requirement->id,
                'doc' => $requirement->doc,
                'type' => $requirement->type,
                'priority' => $requirement->priority,
                'text' => mb_strimwidth($requirement->text, 0, 100, '…'),
            ] + $this->coverage->summarize($requirement))
            ->when($data['only_gaps'] ?? false, fn ($rows) => $rows->filter(fn (array $row): bool => ! $row['covered'] || $row['open_finding_count'] > 0))
            ->values();

        return Response::structured([
            'total' => $rows->count(),
            'limit' => $limit,
            'offset' => $offset,
            'results' => $rows->slice($offset, $limit)->values()->all(),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'project_id' => $schema->string()->description('Project ULID')->required(),
            'only_gaps' => $schema->boolean()->description('Only return requirements without accepted review coverage or with open findings'),
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
