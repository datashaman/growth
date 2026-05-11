<?php

namespace App\Mcp\Tools\Assurance;

use App\Growth\Assurance\EvidenceBundleBuilder;
use App\Models\Project;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Build a compliance evidence bundle index with canonical project resources, artifact counts, and readiness gate status.')]
class BuildEvidenceBundle extends Tool
{
    public function __construct(private readonly EvidenceBundleBuilder $builder) {}

    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'project_id' => 'required|string|owned_project',
        ]);

        return Response::structured($this->builder->build(Project::findOrFail($data['project_id'])));
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
            'project' => $schema->string()->required(),
            'integrity_level' => $schema->integer()->required(),
            'readiness_status' => $schema->string()->required(),
            'resources' => $schema->object()->required(),
            'counts' => $schema->object()->required(),
            'gates' => $schema->array()->required(),
        ];
    }
}
