<?php

namespace App\Mcp\Resources\Project;

use App\Models\Project;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\MimeType;
use Laravel\Mcp\Server\Contracts\HasUriTemplate;
use Laravel\Mcp\Server\Resource;
use Laravel\Mcp\Support\UriTemplate;

#[Description('Listing of every input source attached to a project — briefs, RFPs, transcripts, contracts, prototype URLs, etc. Shows title, kind, external_ref, uri, and citation count per source. Bodies are not included.')]
#[MimeType('text/markdown')]
class ProjectSourcesResource extends Resource implements HasUriTemplate
{
    public function uriTemplate(): UriTemplate
    {
        return new UriTemplate('growth://projects/{project}/sources');
    }

    public function handle(Request $request): Response
    {
        $id = $request->get('project');
        $project = Project::find($id);

        if (! $project) {
            return Response::error("Project [{$id}] not found.");
        }

        $sources = $project->sources()
            ->withCount('citations')
            ->orderBy('kind')
            ->orderBy('title')
            ->get();

        $md = "# Sources — {$project->name}\n\n";
        $md .= "- **Project id:** `{$project->id}`\n";
        $md .= "- **Total sources:** {$sources->count()}\n\n";

        if ($sources->isEmpty()) {
            $md .= "_No sources captured yet. Use `upsert-source` to attach a brief, RFP, transcript, or prototype URL._\n";

            return Response::text($md);
        }

        foreach ($sources->groupBy('kind') as $kind => $group) {
            $md .= "## {$kind}\n\n";
            foreach ($group as $s) {
                $md .= "- **[{$s->id}]** {$s->title}";
                $bits = [];
                if ($s->external_ref) {
                    $bits[] = "ref: `{$s->external_ref}`";
                }
                if ($s->uri) {
                    $bits[] = "uri: {$s->uri}";
                }
                $bits[] = "{$s->citations_count} citation".($s->citations_count === 1 ? '' : 's');
                $md .= ' — '.implode(' — ', $bits)."\n";
            }
            $md .= "\n";
        }

        return Response::text($md);
    }
}
