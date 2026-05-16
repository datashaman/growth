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

#[Description('Project change-control register with change requests, impacted artifacts, review linkage, decisions, and citations.')]
#[MimeType('text/markdown')]
class ProjectChangesResource extends Resource implements HasUriTemplate
{
    public function uriTemplate(): UriTemplate
    {
        return new UriTemplate('growth://projects/{project}/changes');
    }

    public function handle(Request $request): Response
    {
        $id = $request->get('project');
        $project = Project::with([
            'changeRequests' => fn ($q) => $q
                ->with([
                    'requesterRole:id,name',
                    'review:id,title,type,status',
                    'impacts.impactable',
                    'citations.source',
                ])
                ->orderBy('status')
                ->orderByDesc('priority')
                ->orderBy('title'),
        ])->find($id);

        if (! $project) {
            return Response::error("Project [{$id}] not found.");
        }

        $md = "# {$project->name} — Change Register\n\n";
        $md .= "- **Total changes:** {$project->changeRequests->count()}\n";
        $md .= '- **Open changes:** '.$project->changeRequests->whereIn('status', ['proposed', 'under_review', 'approved', 'deferred'])->count()."\n\n";

        if ($project->changeRequests->isEmpty()) {
            $md .= "_No change requests recorded yet. Use `upsert-change-request` to propose or record a controlled change._\n";

            return Response::text($md);
        }

        foreach ($project->changeRequests->groupBy('status') as $status => $changes) {
            $md .= "## {$status}\n\n";

            foreach ($changes as $change) {
                $bits = [
                    "category={$change->category}",
                    "priority={$change->priority}",
                    'decision='.($change->decision ?? 'pending'),
                ];
                if ($change->requesterRole) {
                    $bits[] = "requester={$change->requesterRole->name}";
                }
                if ($change->review) {
                    $bits[] = "review={$change->review->title}";
                }

                $md .= "### {$change->reference()} — {$change->title}\n\n";
                $md .= '- '.implode('; ', $bits)."\n\n";
                if ($change->description) {
                    $md .= "{$change->description}\n\n";
                }
                if ($change->rationale) {
                    $md .= "**Rationale:** {$change->rationale}\n\n";
                }
                if ($change->decision_rationale) {
                    $md .= "**Decision rationale:** {$change->decision_rationale}\n\n";
                }
                $md .= CitationRenderer::render($change->citations, indent: '');

                $md .= "**Impacts**\n\n";
                if ($change->impacts->isEmpty()) {
                    $md .= "- _No impacted artifacts linked._\n\n";
                } else {
                    foreach ($change->impacts as $impact) {
                        $label = $this->label($impact->impactable, (string) $impact->impactable_type);
                        $description = $impact->description ? " — {$impact->description}" : '';
                        $md .= "- {$impact->impact_kind}: `{$impact->impactable_type}:{$impact->impactable_id}` {$label}{$description}\n";
                    }
                    $md .= "\n";
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
