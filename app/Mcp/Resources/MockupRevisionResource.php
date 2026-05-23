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

#[Name('Mockup Revision')]
#[Description('JSON metadata for a specific spec mockup revision, including raw HTML, preview HTML, and preview screenshot resource URIs.')]
#[MimeType('application/json')]
class MockupRevisionResource extends Resource implements HasUriTemplate
{
    use ReturnsStructuredJson;

    public function uriTemplate(): UriTemplate
    {
        return new UriTemplate('growth://mockups/{mockup}/{revision}');
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
            return Response::error("Revision [{$revisionId}] not found on mockup [{$mockupId}].");
        }

        return $this->json([
            'type' => 'mockup_revision',
            'mockup_id' => $mockup->id,
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
