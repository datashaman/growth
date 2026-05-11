<?php

namespace App\Mcp\Tools\Requirements;

use App\Growth\Lint\RequirementLinter;
use App\Models\Requirement;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Audit one requirement (by id) or every requirement in a project against capability rules and rule. Read-only — does not modify the requirements.')]
class LintRequirement extends Tool
{
    public function __construct(private readonly RequirementLinter $linter) {}

    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'id' => 'required_without:project_id|nullable|string|owned_requirement',
            'project_id' => 'required_without:id|nullable|string|owned_project',
        ]);

        $requirements = isset($data['id'])
            ? Requirement::where('id', $data['id'])->get()
            : Requirement::where('project_id', $data['project_id'])->get();

        $results = [];
        $errorCount = 0;
        $warningCount = 0;

        foreach ($requirements as $req) {
            $findings = $this->linter->check($req);
            foreach ($findings as $f) {
                $f['severity'] === 'error' ? $errorCount++ : $warningCount++;
            }
            $results[] = [
                'id' => $req->id,
                'text' => mb_strimwidth($req->text, 0, 80, '…'),
                'findings' => $findings,
            ];
        }

        return Response::structured([
            'scanned' => count($results),
            'errors' => $errorCount,
            'warnings' => $warningCount,
            'results' => $results,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()
                ->description('Requirement ULID. Provide either this or project_id.'),
            'project_id' => $schema->string()
                ->description('Project ULID — lints every requirement in the project.'),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'scanned' => $schema->integer()->required(),
            'errors' => $schema->integer()->required(),
            'warnings' => $schema->integer()->required(),
            'results' => $schema->array()->required(),
        ];
    }
}
