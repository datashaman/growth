<?php

namespace App\Mcp\Resources;

use App\Models\Requirement;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\MimeType;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Contracts\HasUriTemplate;
use Laravel\Mcp\Server\Resource;
use Laravel\Mcp\Support\UriTemplate;

#[Name('Requirement Verification Brief')]
#[Description('Context bundle for generating verification cases for a requirement: acceptance checks, mockups, architecture, existing cases, runs, and anomalies.')]
#[MimeType('text/markdown')]
class RequirementVerificationBriefResource extends Resource implements HasUriTemplate
{
    public function uriTemplate(): UriTemplate
    {
        return new UriTemplate('growth://requirements/{requirement}/verification-brief');
    }

    public function handle(Request $request): Response
    {
        $id = $request->get('requirement');
        $requirement = Requirement::with([
            'project.designViews.concerns',
            'project.designViews.elements' => fn ($query) => $query->orderBy('kind')->orderBy('name'),
            'testCases.plan',
            'testCases.runs.anomalies',
            'anomalies',
            'mockups',
            'workItems',
        ])->find($id);

        if (! $requirement) {
            return Response::error("Requirement [{$id}] not found.");
        }

        $project = $requirement->project;
        $md = "# Verification Brief - {$requirement->reference()}\n\n";
        $md .= "Use this brief before generating or refining verification cases. It bundles the context most likely to affect expected results.\n\n";

        $md .= "## Requirement\n\n";
        $md .= "- **Project:** {$project->name}\n";
        $md .= "- **Layer:** {$requirement->doc}\n";
        $md .= "- **Type:** {$requirement->type}\n";
        $md .= "- **Priority:** {$requirement->priority}\n";
        $md .= "- **Text:** {$requirement->text}\n";
        if ($requirement->rationale) {
            $md .= "- **Rationale:** {$requirement->rationale}\n";
        }
        if (! empty($requirement->acceptance_criteria)) {
            $md .= "- **Acceptance checks:**\n";
            foreach ($requirement->acceptance_criteria as $check) {
                $md .= "  - {$check}\n";
            }
        }
        $md .= "\n";

        $md .= "## Existing Verification\n\n";
        if ($requirement->testCases->isEmpty()) {
            $md .= "_No verification cases cover this requirement yet._\n\n";
        } else {
            foreach ($requirement->testCases as $case) {
                $md .= "- **{$case->name}**";
                if ($case->plan) {
                    $md .= " ({$case->plan->level}: {$case->plan->name})";
                }
                $md .= "\n";
                if ($case->objective) {
                    $md .= "  - Objective: {$case->objective}\n";
                }
                $md .= "  - Expected: {$case->expected_results}\n";
                foreach ($case->runs as $run) {
                    $md .= "  - Run {$run->status}";
                    if ($run->run_at) {
                        $md .= " at {$run->run_at->toIso8601String()}";
                    }
                    $md .= "\n";
                    foreach ($run->anomalies as $anomaly) {
                        $md .= "    - Anomaly {$anomaly->severity}/{$anomaly->status}: {$anomaly->summary}\n";
                    }
                }
            }
            $md .= "\n";
        }

        $md .= "## Mockups And Work Items\n\n";
        if ($requirement->mockups->isEmpty()) {
            $md .= "- **Mockups:** none captured.\n";
        } else {
            foreach ($requirement->mockups as $mockup) {
                $md .= "- **Mockup:** {$mockup->name} (`growth://mockups/{$mockup->id}`)\n";
            }
        }
        if ($requirement->workItems->isEmpty()) {
            $md .= "- **Work items:** none linked.\n\n";
        } else {
            foreach ($requirement->workItems as $workItem) {
                $md .= "- **Work item:** {$workItem->reference()} {$workItem->name} ({$workItem->status})\n";
            }
            $md .= "\n";
        }

        $md .= "## Architecture Context\n\n";
        $views = $project->designViews->sortBy(['viewpoint', 'name'])->values();
        if ($views->isEmpty()) {
            $md .= "_No architecture views are captured for this project yet._\n\n";
        } else {
            foreach ($views as $view) {
                $md .= "### {$view->name}\n\n";
                $md .= "- **Viewpoint:** `{$view->viewpoint}`\n";
                if ($view->description) {
                    $md .= "- **Description:** {$view->description}\n";
                }
                if ($view->concerns->isNotEmpty()) {
                    $md .= '- **Concerns:** '.implode('; ', $view->concerns->pluck('text')->all())."\n";
                }
                if ($view->elements->isNotEmpty()) {
                    $md .= "- **Elements relevant to verification design:**\n";
                    foreach ($view->elements as $element) {
                        $md .= "  - [{$element->kind}] {$element->name}";
                        if ($element->type) {
                            $md .= " ({$element->type})";
                        }
                        if ($element->purpose) {
                            $md .= " - {$element->purpose}";
                        }
                        $md .= "\n";
                    }
                }
                $md .= "\n";
            }
        }

        $md .= "## Open Requirement Anomalies\n\n";
        $openAnomalies = $requirement->anomalies->whereNotIn('status', ['resolved', 'closed']);
        if ($openAnomalies->isEmpty()) {
            $md .= "_No open anomalies are linked to this requirement._\n\n";
        } else {
            foreach ($openAnomalies as $anomaly) {
                $md .= "- **{$anomaly->severity}/{$anomaly->status}:** {$anomaly->summary}\n";
            }
            $md .= "\n";
        }

        $md .= "## Verification Guidance\n\n";
        $md .= "- Turn acceptance checks into observable expected results.\n";
        $md .= "- Use architecture context and mockups to cover important states, flows, boundaries, and visual evidence needs.\n";
        $md .= "- Account for existing cases and anomalies before adding duplicate coverage.\n";

        return Response::text($md);
    }
}
