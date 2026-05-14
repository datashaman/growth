<?php

namespace App\Mcp\Tools\Verification;

use App\Growth\Alignment\AlignmentText;
use App\Growth\Lint\TestLinter;
use App\Models\Project;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Lint verification plans, cases, traces, and unresolved critical anomalies.')]
class LintVerification extends Tool
{
    public function __construct(private readonly TestLinter $linter) {}

    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'project_id' => 'required|string|owned_project',
        ]);

        $findings = AlignmentText::sanitizeArray($this->linter->check(Project::findOrFail($data['project_id'])));

        return Response::structured([
            'project_id' => $data['project_id'],
            'errors' => collect($findings)->where('severity', 'error')->count(),
            'warnings' => collect($findings)->where('severity', '!=', 'error')->count(),
            'findings' => $findings,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'project_id' => $schema->string()->description('Project ULID')->required(),
        ];
    }
}
