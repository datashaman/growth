<?php

namespace App\Mcp\Tools;

use App\Growth\Alignment\AlignmentText;
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

#[Description('Run the Growth quality checks across capabilities, architecture, verification, planning, reviews, baselines, and changes.')]
class LintProject extends Tool
{
    public function __construct(
        private readonly BaselineLinter $baselineLinter,
        private readonly ChangeLinter $changeLinter,
        private readonly RequirementLinter $requirementLinter,
        private readonly DesignLinter $designLinter,
        private readonly TestLinter $testLinter,
        private readonly PmpLinter $planLinter,
        private readonly ReviewLinter $reviewLinter,
    ) {}

    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'project_id' => 'required|string|owned_project',
        ]);

        $project = Project::findOrFail($data['project_id']);

        $capabilityFindings = [];
        foreach ($project->requirements as $capability) {
            foreach ($this->requirementLinter->check($capability) as $finding) {
                $capabilityFindings[] = $finding + [
                    'subject_type' => 'capability',
                    'subject_id' => $capability->id,
                ];
            }
        }

        $sections = AlignmentText::sanitizeArray([
            'baselines' => $this->baselineLinter->check($project),
            'changes' => $this->changeLinter->check($project),
            'capabilities' => $capabilityFindings,
            'architecture' => $this->designLinter->check($project),
            'verification' => $this->testLinter->check($project),
            'planning' => $this->planLinter->check($project),
            'reviews' => $this->reviewLinter->check($project),
        ]);

        $errors = 0;
        $warnings = 0;
        foreach ($sections as $findings) {
            foreach ($findings as $finding) {
                $finding['severity'] === 'error' ? $errors++ : $warnings++;
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
            'project_id' => $schema->string()->description('Project ULID')->required(),
        ];
    }
}
