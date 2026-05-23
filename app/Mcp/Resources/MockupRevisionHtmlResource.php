<?php

namespace App\Mcp\Resources;

use App\Models\SpecMockup;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\MimeType;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Contracts\HasUriTemplate;
use Laravel\Mcp\Server\Resource;
use Laravel\Mcp\Support\UriTemplate;

#[Name('Mockup Revision HTML')]
#[Description('Raw HTML for a specific spec mockup revision.')]
#[MimeType('text/html')]
class MockupRevisionHtmlResource extends Resource implements HasUriTemplate
{
    public function uriTemplate(): UriTemplate
    {
        return new UriTemplate('growth://mockups/{mockup}/{revision}/html');
    }

    public function handle(Request $request): Response
    {
        $mockupId = $request->get('mockup');
        $revisionId = $request->get('revision');

        $mockup = SpecMockup::with('revisions')->find($mockupId);

        if (! $mockup) {
            return Response::error("Mockup [{$mockupId}] not found.");
        }

        $revision = $mockup->revisions->firstWhere('id', $revisionId);

        if (! $revision) {
            return Response::error("Revision [{$revisionId}] not found for mockup [{$mockupId}].");
        }

        return Response::text($revision->html);
    }
}
