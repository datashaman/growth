<?php

namespace App\Mcp\Tools\Assurance;

use App\Growth\Assurance\ContradictionScanner;
use App\Models\Project;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('Scan for cross-artifact contradictions between work status, open anomalies, failed checks, deployments, and rejected implemented changes.')]
class ScanContradictions extends Tool
{
    public function __construct(private readonly ContradictionScanner $scanner) {}

    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'project_id' => 'required|string|owned_project',
        ]);

        return Response::structured($this->scanner->scan(Project::findOrFail($data['project_id'])));
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
            'contradictions' => $schema->integer()->required(),
            'findings' => $schema->array()->required(),
        ];
    }
}
