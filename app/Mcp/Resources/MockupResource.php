<?php

namespace App\Mcp\Resources;

use App\Models\SpecMockup;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\MimeType;
use Laravel\Mcp\Server\Contracts\HasUriTemplate;
use Laravel\Mcp\Server\Resource;
use Laravel\Mcp\Support\UriTemplate;

#[Description('The current rendered HTML of a spec mockup — a renderable visual companion to a work item or requirement that a client can attach as context.')]
#[MimeType('text/html')]
class MockupResource extends Resource implements HasUriTemplate
{
    public function uriTemplate(): UriTemplate
    {
        return new UriTemplate('growth://mockups/{mockup}');
    }

    public function handle(Request $request): Response
    {
        $id = $request->get('mockup');

        // SpecMockup is scoped by owner workspace, so an unknown or
        // cross-workspace id simply finds nothing.
        $mockup = SpecMockup::with('currentRevision')->find($id);

        if (! $mockup) {
            return Response::error("Mockup [{$id}] not found.");
        }

        if (! $mockup->currentRevision) {
            return Response::error("Mockup [{$id}] has no revisions yet.");
        }

        return Response::text($mockup->currentRevision->html);
    }
}
