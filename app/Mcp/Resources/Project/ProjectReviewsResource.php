<?php

namespace App\Mcp\Resources\Project;

use App\Growth\Trace\TraceResolver;
use App\Mcp\Resources\Support\CitationRenderer;
use App\Models\Project;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\MimeType;
use Laravel\Mcp\Server\Contracts\HasUriTemplate;
use Laravel\Mcp\Server\Resource;
use Laravel\Mcp\Support\UriTemplate;

#[Description('Review and audit record for a project, including targets, decisions, and findings.')]
#[MimeType('text/markdown')]
class ProjectReviewsResource extends Resource implements HasUriTemplate
{
    public function uriTemplate(): UriTemplate
    {
        return new UriTemplate('growth://projects/{project}/reviews');
    }

    public function handle(Request $request): Response
    {
        $id = $request->get('project');
        $project = Project::with([
            'reviewPlans' => fn ($q) => $q
                ->withCount('reviews')
                ->orderBy('type')
                ->orderBy('name'),
            'reviews' => fn ($q) => $q
                ->with([
                    'reviewPlan:id,name,type',
                    'ownerRole:id,name',
                    'participants.role:id,name',
                    'targets.reviewable',
                    'findings.ownerRole:id,name',
                    'findings.reviewable',
                    'citations.source',
                    'findings.citations.source',
                ])
                ->orderBy('planned_at')
                ->orderBy('title'),
        ])->find($id);

        if (! $project) {
            return Response::error("Project [{$id}] not found.");
        }

        $md = "# {$project->name} — Review Record\n\n";
        $md .= "Record of management reviews, technical reviews, inspections, walkthroughs, and audits.\n\n";
        $md .= "- **Review plans:** {$project->reviewPlans->count()}\n";
        $md .= "- **Total reviews:** {$project->reviews->count()}\n";
        $md .= '- **Open findings:** '.$project->reviewFindings()->where('status', 'open')->count()."\n\n";

        if ($project->reviewPlans->isNotEmpty()) {
            $md .= "## Review Plans\n\n";
            foreach ($project->reviewPlans as $plan) {
                $md .= "- **{$plan->name}** ({$plan->type}, {$plan->reviews_count} reviews)\n";
                if ($plan->objective) {
                    $md .= "  - {$plan->objective}\n";
                }
                if ($plan->expected_responsibilities) {
                    $md .= '  - Expected roles: '.implode(', ', $plan->expected_responsibilities)."\n";
                }
            }
            $md .= "\n";
        }

        if ($project->reviews->isEmpty()) {
            $md .= "_No reviews recorded yet. Use `upsert-review` to plan or record a review._\n";

            return Response::text($md);
        }

        foreach ($project->reviews->groupBy('type') as $type => $reviews) {
            $md .= '## '.str_replace('_', ' ', (string) $type)."\n\n";

            foreach ($reviews as $review) {
                $bits = [
                    "status={$review->status}",
                    'decision='.($review->decision ?? 'pending'),
                ];
                if ($review->ownerRole) {
                    $bits[] = "owner={$review->ownerRole->name}";
                }
                if ($review->planned_at) {
                    $bits[] = 'planned='.$review->planned_at->toDateString();
                }
                if ($review->held_at) {
                    $bits[] = 'held='.$review->held_at->toDateString();
                }

                $md .= "### {$review->title}\n\n";
                $md .= '- '.implode('; ', $bits)."\n\n";
                if ($review->reviewPlan) {
                    $md .= "- Review plan: {$review->reviewPlan->name}\n\n";
                }
                if ($review->objective) {
                    $md .= "{$review->objective}\n\n";
                }
                $md .= CitationRenderer::render($review->citations, indent: '');

                $md .= "**Participants**\n\n";
                if ($review->participants->isEmpty()) {
                    $md .= "- _No review participants recorded._\n\n";
                } else {
                    foreach ($review->participants->groupBy('responsibility') as $responsibility => $participants) {
                        foreach ($participants as $participant) {
                            $signoff = $participant->signed_off_at
                                ? 'signed off '.$participant->signed_off_at->toDateString()
                                : 'no signoff';
                            $md .= "- {$responsibility}: {$participant->role?->name} ({$participant->attendance_status}, {$signoff})\n";
                            if ($participant->notes) {
                                $md .= "  - {$participant->notes}\n";
                            }
                        }
                    }
                    $md .= "\n";
                }

                $md .= "**Targets**\n\n";
                if ($review->targets->isEmpty()) {
                    $md .= "- _No target artifacts linked._\n\n";
                } else {
                    foreach ($review->targets as $target) {
                        $label = $this->label($target->reviewable, (string) $target->reviewable_type);
                        $context = $target->context ? " — {$target->context}" : '';
                        $md .= "- `{$target->reviewable_type}:{$target->reviewable_id}` {$label}{$context}\n";
                    }
                    $md .= "\n";
                }

                if ($review->entry_criteria) {
                    $md .= "**Entry criteria**\n\n";
                    foreach ($review->entry_criteria as $criterion) {
                        $md .= "- {$criterion}\n";
                    }
                    $md .= "\n";
                }

                if ($review->exit_criteria) {
                    $md .= "**Exit criteria**\n\n";
                    foreach ($review->exit_criteria as $criterion) {
                        $md .= "- {$criterion}\n";
                    }
                    $md .= "\n";
                }

                if ($review->findings->isNotEmpty()) {
                    $md .= "**Findings**\n\n";
                    foreach ($review->findings as $finding) {
                        $owner = $finding->ownerRole?->name ?? 'unassigned';
                        $target = $finding->reviewable_type
                            ? " against `{$finding->reviewable_type}:{$finding->reviewable_id}`"
                            : '';
                        $md .= "- **{$finding->title}** ({$finding->severity}, {$finding->status}, owner={$owner}){$target}\n";
                        if ($finding->description) {
                            $md .= "  - {$finding->description}\n";
                        }
                        if ($finding->disposition) {
                            $md .= "  - Disposition: {$finding->disposition}\n";
                        }
                        $md .= CitationRenderer::render($finding->citations);
                    }
                    $md .= "\n";
                }

                if ($review->summary) {
                    $md .= "**Summary**\n\n{$review->summary}\n\n";
                }
            }
        }

        return Response::text($md);
    }

    private function label(?object $model, string $type): string
    {
        if (! $model) {
            return '';
        }

        return (new TraceResolver)->labelFor($model, $type);
    }
}
