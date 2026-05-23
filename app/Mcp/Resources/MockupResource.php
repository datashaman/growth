<?php

namespace App\Mcp\Resources;

use App\Mcp\Resources\Concerns\ReturnsStructuredJson;
use App\Models\SpecMockup;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\MimeType;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Contracts\HasUriTemplate;
use Laravel\Mcp\Server\Resource;
use Laravel\Mcp\Support\UriTemplate;

#[Name('Mockup')]
#[Description('JSON metadata for a spec mockup current revision, including raw HTML, preview HTML, and preview screenshot resource URIs.')]
#[MimeType('application/json')]
class MockupResource extends Resource implements HasUriTemplate
{
    use ReturnsStructuredJson;

    public function uriTemplate(): UriTemplate
    {
        return new UriTemplate('growth://mockups/{mockup}');
    }

    public function handle(Request $request): Response
    {
        $id = $request->get('mockup');

        $mockup = SpecMockup::with('currentRevision')->find($id);

        if (! $mockup) {
            return Response::error("Mockup [{$id}] not found.");
        }

        if (! $mockup->currentRevision) {
            return Response::error("Mockup [{$id}] has no revisions yet.");
        }

        $revision = $mockup->currentRevision;

        return $this->json([
            'type' => 'mockup',
            'id' => $mockup->id,
            'owner_type' => $mockup->owner_type,
            'owner_id' => $mockup->owner_id,
            'name' => $mockup->name,
            'revision' => [
                'id' => $revision->id,
                'number' => $revision->number,
                'created_at' => $revision->created_at?->toIso8601String(),
            ],
            'html' => [
                'uri' => "growth://mockups/{$mockup->id}/{$revision->id}/html",
                'mime_type' => 'text/html',
            ],
            'preview' => [
                'uri' => "growth://mockups/{$mockup->id}/{$revision->id}/preview",
                'mime_type' => 'text/html',
            ],
            'screenshot' => [
                'uri' => "growth://mockups/{$mockup->id}/{$revision->id}/screenshot",
                'mime_type' => 'image/png',
            ],
        ]);
    }
}
