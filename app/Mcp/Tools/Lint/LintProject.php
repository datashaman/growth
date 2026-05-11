<?php

namespace App\Mcp\Tools\Lint;

use App\Growth\Lint\BaselineLinter;
use App\Growth\Lint\ChangeLinter;
use App\Growth\Lint\DesignLinter;
use App\Growth\Lint\PmpLinter;
use App\Growth\Lint\RequirementLinter;
use App\Growth\Lint\ReviewLinter;
use App\Growth\Lint\TestLinter;
use App\Models\Project;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Run all project linters (requirements quality, design coverage, verification coverage, delivery planning, review readiness, baselines, and change control) and return a combined report.')]
class LintProject extends Tool
{
    public function __construct(
        private readonly BaselineLinter $baselineLinter,
        private readonly ChangeLinter $changeLinter,
        private readonly RequirementLinter $requirementLinter,
        private readonly DesignLinter $designLinter,
        private readonly TestLinter $testLinter,
        private readonly PmpLinter $pmpLinter,
        private readonly ReviewLinter $reviewLinter,
    ) {}

    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'project_id' => 'required|string|owned_project',
        ]);

        $project = Project::find($data['project_id']);

        $baselineFindings = $this->baselineLinter->check($project);
        $changeFindings = $this->changeLinter->check($project);

        $requirementFindings = [];
        foreach ($project->requirements as $req) {
            foreach ($this->requirementLinter->check($req) as $f) {
                $requirementFindings[] = $f + [
                    'subject_type' => 'requirement',
                    'subject_id' => $req->id,
                ];
            }
        }

        $designFindings = $this->designLinter->check($project);
        $testFindings = $this->testLinter->check($project);
        $pmpFindings = $this->pmpLinter->check($project);
        $reviewFindings = $this->reviewLinter->check($project);

        $sections = [
            'baselines' => $baselineFindings,
            'changes' => $changeFindings,
            'requirements' => $requirementFindings,
            'design' => $designFindings,
            'tests' => $testFindings,
            'pmp' => $pmpFindings,
            'reviews' => $reviewFindings,
        ];

        $errors = 0;
        $warnings = 0;
        foreach ($sections as $findings) {
            foreach ($findings as $f) {
                $f['severity'] === 'error' ? $errors++ : $warnings++;
            }
        }

        return Response::structured([
            'project_id' => $project->id,
            'errors' => $errors,
            'warnings' => $warnings,
            'sections' => $sections,
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
            'sections' => $schema->object()->required(),
        ];
    }
}
