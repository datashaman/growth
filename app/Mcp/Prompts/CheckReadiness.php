<?php

namespace App\Mcp\Prompts;

use App\Growth\Alignment\AlignmentText;
use App\Growth\Assurance\ReadinessGateEvaluator;
use App\Mcp\Prompts\Concerns\CompletesProjectId;
use App\Models\Project;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Contracts\Completable;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Prompts\Argument;

#[Name('check-readiness')]
#[Description('Summarize Growth readiness gates and recommend the next corrective action.')]
class CheckReadiness extends Prompt implements Completable
{
    use CompletesProjectId;

    public function __construct(private readonly ReadinessGateEvaluator $readiness) {}

    public function arguments(): array
    {
        return [
            new Argument(
                name: 'project_id',
                description: 'Project ULID.',
                required: true,
            ),
        ];
    }

    /**
     * @return array<int, Response>
     */
    public function handle(Request $request): array
    {
        $data = $request->validate([
            'project_id' => 'required|string|owned_project',
        ]);

        $project = Project::findOrFail($data['project_id']);
        $readiness = AlignmentText::sanitizeArray($this->readiness->evaluate($project));
        $gates = collect($readiness['gates'])
            ->map(fn (array $gate): string => "- {$gate['id']}: {$gate['status']} ({$gate['errors']} errors, {$gate['warnings']} warnings)")
            ->implode("\n");

        $system = <<<'MD'
You are checking whether a Growth project is ready for the next delivery decision.

Use `evaluate-readiness-gates`, `build-evidence-bundle`, and the relevant lint/upsert tools to recommend the smallest corrective action. Be explicit about which gate blocks progress.
MD;

        $user = <<<MD
Project: {$project->name} (`{$project->id}`)
Overall readiness: {$readiness['status']}

Gates:
{$gates}

Recommend the next action.
MD;

        return [
            Response::text($system)->asAssistant(),
            Response::text($user),
        ];
    }
}
