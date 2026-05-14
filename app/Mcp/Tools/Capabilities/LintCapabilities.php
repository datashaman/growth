<?php

namespace App\Mcp\Tools\Capabilities;

use App\Growth\Alignment\AlignmentText;
use App\Growth\Lint\RequirementLinter;
use App\Models\Requirement;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Audit one capability or every capability in a project for clarity, singularity, and verifiability.')]
class LintCapabilities extends Tool
{
    public function __construct(private readonly RequirementLinter $linter) {}

    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'id' => 'required_without:project_id|nullable|string|owned_requirement',
            'project_id' => 'required_without:id|nullable|string|owned_project',
        ]);

        $capabilities = isset($data['id'])
            ? Requirement::where('id', $data['id'])->get()
            : Requirement::where('project_id', $data['project_id'])->get();

        $results = [];
        $errors = 0;
        $warnings = 0;

        foreach ($capabilities as $capability) {
            $findings = AlignmentText::sanitizeArray($this->linter->check($capability));
            foreach ($findings as $finding) {
                $finding['severity'] === 'error' ? $errors++ : $warnings++;
            }
            $results[] = [
                'id' => $capability->id,
                'layer' => AlignmentText::docToLayer($capability->doc),
                'text' => mb_strimwidth($capability->text, 0, 80, '...'),
                'findings' => $findings,
            ];
        }

        return Response::structured([
            'scanned' => count($results),
            'errors' => $errors,
            'warnings' => $warnings,
            'results' => $results,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->description('Capability ULID. Provide either this or project_id.'),
            'project_id' => $schema->string()->description('Project ULID. Audits every capability in the project.'),
        ];
    }
}
