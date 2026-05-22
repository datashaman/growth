<?php

namespace App\Mcp\Resources;

use App\Models\Review;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\MimeType;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Contracts\HasUriTemplate;
use Laravel\Mcp\Server\Resource;
use Laravel\Mcp\Support\UriTemplate;

#[Name('Review Brief')]
#[Description('Context bundle for preparing or refining a review: plan, targets, participants, findings, decisions, change requests, and architecture context.')]
#[MimeType('text/markdown')]
class ReviewBriefResource extends Resource implements HasUriTemplate
{
    public function uriTemplate(): UriTemplate
    {
        return new UriTemplate('growth://reviews/{review}/review-brief');
    }

    public function handle(Request $request): Response
    {
        $id = $request->get('review');
        $review = Review::with([
            'project.designViews.concerns',
            'project.designViews.elements' => fn ($query) => $query->orderBy('kind')->orderBy('name'),
            'reviewPlan',
            'ownerRole',
            'targets.reviewable',
            'participants.role',
            'findings.reviewable',
            'decisionEvents',
            'changeRequests.impacts.impactable',
        ])->find($id);

        if (! $review) {
            return Response::error("Review [{$id}] not found.");
        }

        $project = $review->project;
        $md = "# Review Brief - {$review->title}\n\n";
        $md .= "Use this brief before preparing, refining, holding, or closing the review. It bundles the context most likely to affect the review artifact and evidence trail.\n\n";

        $md .= "## Review\n\n";
        $md .= "- **Project:** {$project->name}\n";
        $md .= "- **Type:** {$review->type}\n";
        $md .= "- **Status:** {$review->status}\n";
        if ($review->decision) {
            $md .= "- **Decision:** {$review->decision}\n";
        }
        if ($review->objective) {
            $md .= "- **Objective:** {$review->objective}\n";
        }
        if ($review->ownerRole) {
            $md .= "- **Owner role:** {$review->ownerRole->name}\n";
        }
        $md .= "\n";

        $md .= "## Review Plan\n\n";
        if (! $review->reviewPlan) {
            $md .= "_No review plan is linked._\n\n";
        } else {
            $plan = $review->reviewPlan;
            $md .= "- **Plan:** {$plan->name}\n";
            if ($plan->objective) {
                $md .= "- **Plan objective:** {$plan->objective}\n";
            }
            if ($plan->procedure) {
                $md .= "- **Procedure:** {$plan->procedure}\n";
            }
            $md .= $this->checklist('Entry criteria', $plan->entry_criteria);
            $md .= $this->checklist('Exit criteria', $plan->exit_criteria);
            $md .= $this->checklist('Expected responsibilities', $plan->expected_responsibilities);
            $md .= $this->checklist('Checklist', $plan->checklist);
            $md .= "\n";
        }

        $md .= "## Targets\n\n";
        if ($review->targets->isEmpty()) {
            $md .= "_No target artifacts are linked yet._\n\n";
        } else {
            foreach ($review->targets as $target) {
                $md .= "- **{$target->reviewable_type}:** {$this->artifactLabel($target->reviewable)}";
                if ($target->context) {
                    $md .= " - {$target->context}";
                }
                $md .= "\n";
            }
            $md .= "\n";
        }

        $md .= "## Participants And Findings\n\n";
        if ($review->participants->isEmpty()) {
            $md .= "- **Participants:** none assigned.\n";
        } else {
            foreach ($review->participants as $participant) {
                $roleName = $participant->role?->name ?? 'Unknown role';
                $md .= "- **{$participant->responsibility}:** {$roleName} ({$participant->attendance_status})\n";
            }
        }
        if ($review->findings->isEmpty()) {
            $md .= "- **Findings:** none captured.\n\n";
        } else {
            foreach ($review->findings as $finding) {
                $md .= "- **{$finding->severity}/{$finding->status}:** {$finding->title}";
                if ($finding->reviewable) {
                    $md .= " ({$this->artifactLabel($finding->reviewable)})";
                }
                if ($finding->description) {
                    $md .= " - {$finding->description}";
                }
                $md .= "\n";
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
                    $md .= "- **Elements relevant to review scope:**\n";
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

        $md .= "## Decisions And Change Requests\n\n";
        if ($review->decisionEvents->isEmpty()) {
            $md .= "- **Decision events:** none recorded.\n";
        } else {
            foreach ($review->decisionEvents as $event) {
                $md .= "- **Decision event:** {$event->from_status} -> {$event->to_status}";
                if ($event->to_decision) {
                    $md .= " / {$event->to_decision}";
                }
                if ($event->rationale) {
                    $md .= " - {$event->rationale}";
                }
                $md .= "\n";
            }
        }
        if ($review->changeRequests->isEmpty()) {
            $md .= "- **Change requests:** none linked.\n\n";
        } else {
            foreach ($review->changeRequests as $change) {
                $md .= "- **{$change->reference()}:** {$change->title} ({$change->status}";
                if ($change->decision) {
                    $md .= ", {$change->decision}";
                }
                $md .= ") `growth://change-requests/{$change->id}/change-impact-brief`\n";
            }
            $md .= "\n";
        }

        $md .= "## Review Guidance\n\n";
        $md .= "- Ground the objective, criteria, summary, and decision in the target artifacts and review plan.\n";
        $md .= "- Use architecture context to focus questions on boundaries, responsibilities, data flow, and user-facing behavior that can affect the reviewed artifacts.\n";
        $md .= "- Account for existing findings, decisions, participants, and linked change requests before adding duplicate review work.\n";

        return Response::text($md);
    }

    /**
     * @param  list<string>|null  $items
     */
    private function checklist(string $label, ?array $items): string
    {
        if (empty($items)) {
            return '';
        }

        $md = "- **{$label}:**\n";
        foreach ($items as $item) {
            $md .= "  - {$item}\n";
        }

        return $md;
    }

    private function artifactLabel(?object $artifact): string
    {
        if (! $artifact) {
            return 'missing artifact';
        }

        $reference = method_exists($artifact, 'reference') ? $artifact->reference() : null;
        $name = $artifact->name ?? $artifact->title ?? $artifact->text ?? $artifact->summary ?? $artifact->id;

        return trim("{$reference} {$name}");
    }
}
