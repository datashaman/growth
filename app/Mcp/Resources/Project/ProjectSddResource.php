<?php

namespace App\Mcp\Resources\Project;

use App\Mcp\Resources\Support\CitationRenderer;
use App\Models\DesignView;
use App\Models\Project;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\MimeType;
use Laravel\Mcp\Server\Contracts\HasUriTemplate;
use Laravel\Mcp\Server\Resource;
use Laravel\Mcp\Support\UriTemplate;

#[Description('Architecture description assembled from the project\'s viewpoints, design views, and design elements.')]
#[MimeType('text/markdown')]
class ProjectSddResource extends Resource implements HasUriTemplate
{
    public function uriTemplate(): UriTemplate
    {
        return new UriTemplate('growth://projects/{project}/sdd');
    }

    public function handle(Request $request): Response
    {
        $project = Project::with([
            'designViews.elements' => fn ($q) => $q->orderBy('kind')->orderBy('name'),
            'designViews.concerns',
            'designViews.citations.source',
            'customViewpoints' => fn ($q) => $q->orderBy('name'),
            'customViewpoints.citations.source',
        ])->find($request->get('project'));

        if (! $project) {
            return Response::error("Project [{$request->get('project')}] not found.");
        }

        $views = $project->designViews->sortBy(['viewpoint', 'name'])->values();
        $usedBuiltins = $views->pluck('viewpoint')->intersect(DesignView::BUILTIN_VIEWPOINTS)->unique()->sort()->values();

        $md = "# Software Design Description — {$project->name}\n\n";
        $md .= "_Rigor level {$project->rigor_level}_\n\n";

        $md .= "## 3. Viewpoints in use\n\n";
        $md .= "### 3.1 Built-in viewpoints\n\n";
        if ($usedBuiltins->isEmpty()) {
            $md .= "_None._\n\n";
        } else {
            foreach ($usedBuiltins as $vp) {
                $md .= "- `{$vp}`\n";
            }
            $md .= "\n";
        }

        $md .= "### 3.2 Custom viewpoints\n\n";
        if ($project->customViewpoints->isEmpty()) {
            $md .= "_None defined._\n\n";
        } else {
            foreach ($project->customViewpoints as $vp) {
                $md .= "#### {$vp->name}\n\n";
                $md .= '- **Concerns:** '.implode(', ', $vp->concerns)."\n";
                $md .= '- **Element types:** '.implode(', ', $vp->element_types)."\n";
                $md .= '- **Languages:** '.implode(', ', $vp->languages)."\n";
                if ($vp->source) {
                    $md .= "- **Source:** {$vp->source}\n";
                }
                $md .= CitationRenderer::render($vp->citations, indent: '');
                $md .= "\n";
            }
        }

        $md .= "## 4. Design views\n\n";
        if ($views->isEmpty()) {
            $md .= "_None defined._\n\n";
        } else {
            foreach ($views as $v) {
                $md .= "### {$v->name}\n\n";
                $md .= "- **Viewpoint:** `{$v->viewpoint}`\n";
                if ($v->description) {
                    $md .= "- **Description:** {$v->description}\n";
                }

                if ($v->concerns->isNotEmpty()) {
                    $md .= "- **Concerns addressed:**\n";
                    foreach ($v->concerns as $c) {
                        $md .= "  - {$c->text}\n";
                    }
                }

                $md .= CitationRenderer::render($v->citations, indent: '');

                if ($v->elements->isNotEmpty()) {
                    $md .= "\n**Elements**\n\n";
                    foreach ($v->elements as $e) {
                        $md .= "- **[{$e->kind}] {$e->name}**";
                        if ($e->type) {
                            $md .= " _({$e->type})_";
                        }
                        $md .= "\n";
                        if ($e->purpose) {
                            $md .= "  - _Purpose:_ {$e->purpose}\n";
                        }
                    }
                }
                $md .= "\n";
            }
        }

        return Response::text($md);
    }
}
