<?php

namespace App\Mcp\Tools\Plan;

use App\Growth\Lint\PmpLinter;
use App\Models\Project;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Lint a project against delivery planning (PMP) completeness rules. Returns the same findings as lint-project sections.planning — use this when you only need planning findings and do not want to filter the full lint-project response. Findings include severity, rule code, message, and the subject artifact.')]
class PmpLint extends Tool
{
    public function __construct(private readonly PmpLinter $linter) {}

    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'project_id' => 'required|string|owned_project',
        ]);

        $project = Project::find($data['project_id']);
        $findings = $this->linter->check($project);

        $errors = 0;
        $warnings = 0;
        foreach ($findings as $f) {
            $f['severity'] === 'error' ? $errors++ : $warnings++;
        }

        return Response::structured([
            'project_id' => $project->id,
            'errors' => $errors,
            'warnings' => $warnings,
            'findings' => $findings,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'project_id' => $schema->string()
                ->description('Project ULID')
                ->required(),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'project_id' => $schema->string()->required(),
            'errors' => $schema->integer()->required(),
            'warnings' => $schema->integer()->required(),
            'findings' => $schema->array()->required(),
        ];
    }
}
