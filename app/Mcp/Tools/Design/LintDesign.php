<?php

namespace App\Mcp\Tools\Design;

use App\Growth\Lint\DesignLinter;
use App\Models\Project;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Lint a project\'s design (architecture coverage rules-4.5) for completeness gaps: views with no design elements, views that don\'t address any concerns, custom viewpoints with no consuming view, and projects with concerns but no views.')]
class LintDesign extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'project_id' => 'required|string|owned_project',
        ]);

        $project = Project::find($data['project_id']);
        $findings = (new DesignLinter)->check($project);

        $errors = 0;
        $warnings = 0;
        foreach ($findings as $f) {
            if ($f['severity'] === 'error') {
                $errors++;
            } else {
                $warnings++;
            }
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
