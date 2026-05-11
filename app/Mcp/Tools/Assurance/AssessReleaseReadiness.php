<?php

namespace App\Mcp\Tools\Assurance;

use App\Growth\Assurance\ReleaseReadinessAssessor;
use App\Models\Project;
use App\Models\Release;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Assess risk-adjusted release readiness from lifecycle gates, high-exposure risks, check evidence, and deployment state.')]
class AssessReleaseReadiness extends Tool
{
    public function __construct(private readonly ReleaseReadinessAssessor $assessor) {}

    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'project_id' => 'required|string|owned_project',
            'release_id' => 'nullable|string|owned_release',
        ]);

        $project = Project::findOrFail($data['project_id']);
        $release = isset($data['release_id']) ? Release::findOrFail($data['release_id']) : null;
        if ($release && $release->project_id !== $project->id) {
            throw ValidationException::withMessages([
                'release_id' => 'Release must belong to the selected project.',
            ]);
        }

        return Response::structured($this->assessor->assess($project, $release));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'project_id' => $schema->string()->description('Project ULID')->required(),
            'release_id' => $schema->string()->description('Optional release ULID to assess'),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'project_id' => $schema->string()->required(),
            'release_id' => $schema->string(),
            'release_version' => $schema->string(),
            'status' => $schema->string()->required(),
            'blockers' => $schema->array()->required(),
            'readiness_status' => $schema->string()->required(),
            'risk_summary' => $schema->object()->required(),
            'delivery_summary' => $schema->object()->required(),
            'risks' => $schema->array()->required(),
        ];
    }
}
