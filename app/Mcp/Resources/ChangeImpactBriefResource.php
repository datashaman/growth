<?php

namespace App\Mcp\Resources;

use App\Models\ChangeRequest;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\MimeType;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Contracts\HasUriTemplate;
use Laravel\Mcp\Server\Resource;
use Laravel\Mcp\Support\UriTemplate;

#[Name('Change Impact Brief')]
#[Description('Context bundle for analyzing or refining a change request: impacted artifacts, review linkage, approval events, requirements, work items, and architecture context.')]
#[MimeType('text/markdown')]
class ChangeImpactBriefResource extends Resource implements HasUriTemplate
{
    public function uriTemplate(): UriTemplate
    {
        return new UriTemplate('growth://change-requests/{change_request}/change-impact-brief');
    }

    public function handle(Request $request): Response
    {
        $id = $request->get('change_request');
        $change = ChangeRequest::with([
            'project.designViews.concerns',
            'project.designViews.elements' => fn ($query) => $query->orderBy('kind')->orderBy('name'),
            'requesterRole',
            'review.targets.reviewable',
            'review.findings',
            'impacts.impactable',
            'approvalEvents.recordedBy',
        ])->find($id);

        if (! $change) {
            return Response::error("Change request [{$id}] not found.");
        }

        $project = $change->project;
        $md = "# Change Impact Brief - {$change->reference()} {$change->title}\n\n";
        $md .= "Use this brief before analyzing, refining, approving, rejecting, deferring, or implementing the change request. It bundles the context most likely to affect impact decisions.\n\n";

        $md .= "## Change Request\n\n";
        $md .= "- **Project:** {$project->name}\n";
        $md .= "- **Category:** {$change->category}\n";
        $md .= "- **Status:** {$change->status}\n";
        $md .= "- **Priority:** {$change->priority}\n";
        if ($change->decision) {
            $md .= "- **Decision:** {$change->decision}\n";
        }
        if ($change->requesterRole) {
            $md .= "- **Requester role:** {$change->requesterRole->name}\n";
        }
        if ($change->description) {
            $md .= "- **Description:** {$change->description}\n";
        }
        if ($change->rationale) {
            $md .= "- **Rationale:** {$change->rationale}\n";
        }
        if ($change->decision_rationale) {
            $md .= "- **Decision rationale:** {$change->decision_rationale}\n";
        }
        $md .= "\n";

        $md .= "## Impacted Artifacts\n\n";
        if ($change->impacts->isEmpty()) {
            $md .= "_No impacted artifacts are linked yet. Run `analyze-change-impact` or add explicit impacts before making a decision._\n\n";
        } else {
            foreach ($change->impacts as $impact) {
                $md .= "- **{$impact->impact_kind}:** {$impact->impactable_type} {$this->artifactLabel($impact->impactable)}";
                if ($impact->description) {
                    $md .= " - {$impact->description}";
                }
                $md .= "\n";
            }
            $md .= "\n";
        }

        $md .= "## Review Linkage\n\n";
        if (! $change->review) {
            $md .= "_No review is linked to this change request._\n\n";
        } else {
            $review = $change->review;
            $md .= "- **Review:** {$review->title} ({$review->type}, {$review->status}) `growth://reviews/{$review->id}/review-brief`\n";
            if ($review->decision) {
                $md .= "- **Review decision:** {$review->decision}\n";
            }
            foreach ($review->targets as $target) {
                $md .= "- **Review target:** {$target->reviewable_type} {$this->artifactLabel($target->reviewable)}";
                if ($target->context) {
                    $md .= " - {$target->context}";
                }
                $md .= "\n";
            }
            foreach ($review->findings as $finding) {
                $md .= "- **Review finding:** {$finding->severity}/{$finding->status} {$finding->title}\n";
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
                    $md .= "- **Elements relevant to impact analysis:**\n";
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

        $md .= "## Approval Events\n\n";
        if ($change->approvalEvents->isEmpty()) {
            $md .= "_No approval events are recorded yet._\n\n";
        } else {
            foreach ($change->approvalEvents as $event) {
                $md .= "- **{$event->from_status} -> {$event->to_status}:**";
                if ($event->to_decision) {
                    $md .= " {$event->to_decision}";
                }
                if ($event->rationale) {
                    $md .= " - {$event->rationale}";
                }
                $md .= "\n";
            }
            $md .= "\n";
        }

        $md .= "## Change Guidance\n\n";
        $md .= "- Treat impacted artifacts as a starting point, then use `analyze-change-impact` and trace context to find adjacent requirements, work items, tests, risks, and architecture elements.\n";
        $md .= "- Make the rationale and impact descriptions explicit enough for a reviewer to see what behavior, evidence, or plan changes are expected.\n";
        $md .= "- Reconcile review findings, approval events, and architecture context before recording a decision.\n";

        return Response::text($md);
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
