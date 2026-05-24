<?php

namespace App\Mcp\Resources;

use App\Models\Mockup;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\MimeType;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Contracts\HasUriTemplate;
use Laravel\Mcp\Server\Resource;
use Laravel\Mcp\Support\UriTemplate;

#[Name('Project Mockup HTML')]
#[Description('The current HTML of a named project-level design system mockup — the layout template or a component specimen — addressed by project and name.')]
#[MimeType('text/html')]
class ProjectMockupHtmlResource extends Resource implements HasUriTemplate
{
    public function uriTemplate(): UriTemplate
    {
        return new UriTemplate('growth://projects/{project}/mockups/{name}');
    }

    public function handle(Request $request): Response
    {
        $projectId = $request->get('project');
        $name = $request->get('name');

        $mockup = Mockup::with('currentRevision')
            ->where('owner_type', 'project')
            ->where('owner_id', $projectId)
            ->where('name', $name)
            ->first();

        if (! $mockup) {
            return Response::error("No design system mockup named [{$name}] found for project [{$projectId}].");
        }

        if (! $mockup->currentRevision) {
            return Response::error("Design system mockup [{$name}] has no revisions yet.");
        }

        return Response::text($mockup->currentRevision->html);
    }
}
