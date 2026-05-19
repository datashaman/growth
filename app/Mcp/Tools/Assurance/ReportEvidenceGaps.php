<?php

namespace App\Mcp\Tools\Assurance;

use App\Growth\Assurance\EvidenceGapReporter;
use App\Models\Project;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('Report missing evidence and orphaned decision records across work items, reviews, changes, releases, and deployments.')]
class ReportEvidenceGaps extends Tool
{
    public function __construct(private readonly EvidenceGapReporter $reporter) {}

    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'project_id' => 'required|string|owned_project',
        ]);

        return Response::structured($this->reporter->report(Project::findOrFail($data['project_id'])));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'project_id' => $schema->string()->description('Project ULID')->required(),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'project_id' => $schema->string()->required(),
            'gaps' => $schema->integer()->required(),
            'findings' => $schema->array()->required(),
        ];
    }
}
