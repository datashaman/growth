<?php

namespace App\Mcp\Resources\Project;

use App\Mcp\Resources\Support\CitationRenderer;
use App\Models\Project;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\MimeType;
use Laravel\Mcp\Server\Contracts\HasUriTemplate;
use Laravel\Mcp\Server\Resource;
use Laravel\Mcp\Support\UriTemplate;

#[Description('Requirements definition assembled from the project\'s stakeholders, concerns, and capabilities.')]
#[MimeType('text/markdown')]
class ProjectSrsResource extends Resource implements HasUriTemplate
{
    public function uriTemplate(): UriTemplate
    {
        return new UriTemplate('growth://projects/{project}/srs');
    }

    public function handle(Request $request): Response
    {
        $project = Project::with([
            'requirements' => fn ($q) => $q->with('citations.source')
                ->orderBy('doc')->orderBy('type')->orderBy('id'),
        ])->find($request->get('project'));

        if (! $project) {
            return Response::error("Project [{$request->get('project')}] not found.");
        }

        $stakeholders = $project->stakeholders()->orderBy('name')->get();
        $concerns = $project->concerns()
            ->with(['raisedBy', 'citations.source'])
            ->orderBy('id')
            ->get();

        $md = "# Software Requirements Definition — {$project->name}\n\n";
        $md .= "_Rigor level {$project->rigor_level}_\n\n";

        if ($project->description) {
            $md .= "## 1. Overview\n\n{$project->description}\n\n";
        }

        $md .= "## 2. Stakeholders\n\n";
        if ($stakeholders->isEmpty()) {
            $md .= "_None defined._\n\n";
        } else {
            foreach ($stakeholders as $s) {
                $md .= "### {$s->name} — {$s->role}\n\n";
                $md .= "- **Kind:** {$s->kind}\n";
                if ($s->description) {
                    $md .= "- **Description:** {$s->description}\n";
                }
                $md .= "\n";
            }
        }

        $md .= "## 3. Stakeholder concerns\n\n";
        if ($concerns->isEmpty()) {
            $md .= "_None recorded._\n\n";
        } else {
            foreach ($concerns as $c) {
                $raisedBy = $c->raisedBy?->name ?? '_unattributed_';
                $md .= "- ({$raisedBy}) {$c->text}\n";
                $md .= CitationRenderer::render($c->citations);
            }
            $md .= "\n";
        }

        $md .= "## 4. Requirements\n\n";
        $groups = $project->requirements->groupBy('doc');
        if ($groups->isEmpty()) {
            $md .= "_None defined._\n\n";
        } else {
            $sections = [
                'strs' => ['4.1', 'Stakeholder requirements'],
                'syrs' => ['4.2', 'System requirements'],
                'srs' => ['4.3', 'Software requirements'],
            ];
            foreach ($sections as $doc => [$num, $label]) {
                if (! $groups->has($doc)) {
                    continue;
                }
                $md .= "### {$num} {$label} ({$doc})\n\n";
                foreach ($groups[$doc]->groupBy('type') as $type => $reqs) {
                    $md .= "#### {$type}\n\n";
                    foreach ($reqs as $r) {
                        $md .= "- **[{$r->id}]** ({$r->priority}) {$r->text}\n";
                        if ($r->rationale) {
                            $md .= "  - _Rationale:_ {$r->rationale}\n";
                        }
                        if ($r->acceptance_criteria) {
                            $md .= "  - _Acceptance criteria:_\n";
                            foreach ($r->acceptance_criteria as $criterion) {
                                $md .= "    - {$criterion}\n";
                            }
                        }
                        if ($r->source) {
                            $md .= "  - _Source:_ {$r->source}\n";
                        }
                        $md .= CitationRenderer::render($r->citations);
                    }
                    $md .= "\n";
                }
            }
        }

        return Response::text($md);
    }
}
