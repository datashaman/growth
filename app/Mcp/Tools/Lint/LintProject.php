<?php

namespace App\Mcp\Tools\Lint;

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

#[Description('Run Growth quality checks for a project. Returns findings grouped into sections (requirements, architecture, verification, planning, reviews, baselines, changes). Pass `sections` to compute only the listed sections; omit it to run them all. `errors` and `warnings` counts cover the returned sections only.')]
class LintProject extends Tool
{
    private const SECTIONS = [
        'baselines',
        'changes',
        'requirements',
        'architecture',
        'verification',
        'planning',
        'reviews',
    ];

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
            'sections' => 'nullable|array',
            'sections.*' => 'in:'.implode(',', self::SECTIONS),
        ]);

        $project = Project::findOrFail($data['project_id']);
        $wanted = isset($data['sections']) && $data['sections'] !== []
            ? array_values(array_unique($data['sections']))
            : self::SECTIONS;

        $compute = [
            'baselines' => fn () => $this->baselineLinter->check($project),
            'changes' => fn () => $this->changeLinter->check($project),
            'requirements' => fn () => $this->requirementFindings($project),
            'architecture' => fn () => $this->designLinter->check($project),
            'verification' => fn () => $this->testLinter->check($project),
            'planning' => fn () => $this->planLinter->check($project),
            'reviews' => fn () => $this->reviewLinter->check($project),
        ];

        $sections = [];
        foreach ($wanted as $name) {
            $sections[$name] = $compute[$name]();
        }
        $sections = AlignmentText::sanitizeArray($sections);

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

    /**
     * @return array<int, array<string, mixed>>
     */
    private function requirementFindings(Project $project): array
    {
        $findings = [];
        foreach ($project->requirements as $requirement) {
            foreach ($this->requirementLinter->check($requirement) as $finding) {
                $findings[] = $finding + [
                    'subject_type' => 'requirement',
                    'subject_id' => $requirement->id,
                ];
            }
        }

        return $findings;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'project_id' => $schema->string()->description('Project ULID')->required(),
            'sections' => $schema->array()
                ->description('Subset of sections to compute. Defaults to all: '.implode(', ', self::SECTIONS).'.')
                ->items($schema->string()->enum(self::SECTIONS)),
        ];
    }
}
