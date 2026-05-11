<?php

namespace App\Mcp\Tools\Changes;

use App\Growth\Lint\ChangeLinter;
use App\Models\Project;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Lint project change requests for control gaps: impacts, review evidence, decisions, rationale, approval, and unresolved impact-analysis items.')]
class LintChanges extends Tool
{
    public function __construct(private readonly ChangeLinter $linter) {}

    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'project_id' => 'required|string|owned_project',
        ]);

        $project = Project::find($data['project_id']);
        $findings = $this->linter->check($project);

        $errors = 0;
        $warnings = 0;
        foreach ($findings as $finding) {
            $finding['severity'] === 'error' ? $errors++ : $warnings++;
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
