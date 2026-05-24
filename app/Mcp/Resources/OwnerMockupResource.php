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

#[Name('Owner Mockup')]
#[Description('The current rendered HTML of a work item or requirement\'s default spec mockup, addressed by the owner rather than the mockup ULID. Lets a client attach the visual companion as context without first looking up the mockup id.')]
#[MimeType('text/html')]
class OwnerMockupResource extends Resource implements HasUriTemplate
{
    public function uriTemplate(): UriTemplate
    {
        return new UriTemplate('growth://owners/{owner_type}/{owner_id}/mockup');
    }

    public function handle(Request $request): Response
    {
        $ownerType = $request->get('owner_type');
        $ownerId = $request->get('owner_id');

        // Mockup is scoped by owner workspace, so a cross-workspace or
        // unknown owner simply finds nothing.
        $mockup = Mockup::with('currentRevision')
            ->where('owner_type', $ownerType)
            ->where('owner_id', $ownerId)
            ->where('name', Mockup::DEFAULT_NAME)
            ->first();

        if (! $mockup) {
            return Response::error("No default mockup found for {$ownerType} [{$ownerId}].");
        }

        if (! $mockup->currentRevision) {
            return Response::error("Mockup [{$mockup->id}] has no revisions yet.");
        }

        return Response::text($mockup->currentRevision->html);
    }
}
