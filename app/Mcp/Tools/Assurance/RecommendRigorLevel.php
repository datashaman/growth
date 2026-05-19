<?php

namespace App\Mcp\Tools\Assurance;

use App\Growth\Alignment\AlignmentText;
use App\Growth\Assurance\ReadinessGateEvaluator;
use App\Models\Project;
use App\Models\Requirement;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Advisory rigor ratchet for an adopted project: report whether the backfill has caught up enough to climb one rigor level. Evaluates the readiness linters with the rigor level hypothetically raised by one — it never persists a change. Bumping rigor stays a confirmed decision made via update-project.')]
class RecommendRigorLevel extends Tool
{
    /**
     * The highest rigor level a project can hold.
     */
    private const CEILING = 4;

    public function __construct(private readonly ReadinessGateEvaluator $evaluator) {}

    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'project_id' => 'required|string|owned_project',
        ]);

        $project = Project::findOrFail($data['project_id']);
        $currentLevel = $project->rigor_level;

        if ($project->adopted_at === null) {
            return Response::structured($this->verdict(
                $project, $currentLevel, adopted: false, atCeiling: false, qualifies: false, nextLevel: null,
            ));
        }

        if ($currentLevel >= self::CEILING) {
            return Response::structured($this->verdict(
                $project, $currentLevel, adopted: true, atCeiling: true, qualifies: false, nextLevel: null,
            ));
        }

        // Pin every requirement's `project` relation to this single instance:
        // RequirementLinter reads rigor indirectly via `requirement->project`,
        // so it must track the hypothetical level flipped on `$project` below.
        $project->setRelation('requirements', $project->requirements()->get()
            ->each(fn (Requirement $requirement) => $requirement->setRelation('project', $project)));

        $nextLevel = $currentLevel + 1;

        $project->rigor_level = $currentLevel;
        $baselineErrorKeys = $this->errorKeys($this->findings($project));

        $project->rigor_level = $nextLevel;
        $nextFindings = $this->findings($project);

        // Leave the in-memory instance as we found it — the ratchet is advisory.
        $project->rigor_level = $currentLevel;

        $blocking = array_values(array_filter(
            $nextFindings,
            fn (array $finding): bool => $finding['severity'] === 'error'
                && ! in_array($this->findingKey($finding), $baselineErrorKeys, true),
        ));
        $warnings = array_values(array_filter(
            $nextFindings,
            fn (array $finding): bool => $finding['severity'] === 'warning',
        ));

        return Response::structured($this->verdict(
            $project, $currentLevel, adopted: true, atCeiling: false,
            qualifies: $blocking === [], nextLevel: $nextLevel, blocking: $blocking, warnings: $warnings,
        ));
    }

    /**
     * Flatten every gate's findings into one list for the given project state.
     *
     * @return list<array<string, mixed>>
     */
    private function findings(Project $project): array
    {
        return collect($this->evaluator->evaluate($project)['gates'])
            ->flatMap(fn (array $gate): array => $gate['findings'])
            ->all();
    }

    /**
     * @param  list<array<string, mixed>>  $findings
     * @return list<string>
     */
    private function errorKeys(array $findings): array
    {
        return array_values(array_map(
            $this->findingKey(...),
            array_filter($findings, fn (array $finding): bool => $finding['severity'] === 'error'),
        ));
    }

    /**
     * @param  array<string, mixed>  $finding
     */
    private function findingKey(array $finding): string
    {
        return implode('|', [
            $finding['rule'],
            $finding['subject_type'] ?? '',
            $finding['subject_id'] ?? '',
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $blocking
     * @param  list<array<string, mixed>>  $warnings
     * @return array<string, mixed>
     */
    private function verdict(
        Project $project,
        int $currentLevel,
        bool $adopted,
        bool $atCeiling,
        bool $qualifies,
        ?int $nextLevel,
        array $blocking = [],
        array $warnings = [],
    ): array {
        return AlignmentText::sanitizeArray([
            'project_id' => $project->id,
            'adopted' => $adopted,
            'current_level' => $currentLevel,
            'at_ceiling' => $atCeiling,
            'qualifies_for_next' => $qualifies,
            'next_level' => $nextLevel,
            'blocking_findings' => $blocking,
            'warnings' => $warnings,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'project_id' => $schema->string()->description('Project ULID')->required(),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'project_id' => $schema->string()->required(),
            'adopted' => $schema->boolean()->description('Whether the project was adopted; the ratchet is not applicable to greenfield projects.')->required(),
            'current_level' => $schema->integer()->description('The project\'s current rigor level.')->required(),
            'at_ceiling' => $schema->boolean()->description('Whether the project is already at the maximum rigor level.')->required(),
            'qualifies_for_next' => $schema->boolean()->description('Whether the project qualifies to climb one rigor level.')->required(),
            'next_level' => $schema->integer()->nullable()->description('The level the project would climb to; null when not applicable or at ceiling.')->required(),
            'blocking_findings' => $schema->array()->description('Error-severity findings introduced by the next level that block advancement.')->required(),
            'warnings' => $schema->array()->description('Warning-severity findings at the next level — they do not block, but are surfaced before a bump.')->required(),
        ];
    }
}
