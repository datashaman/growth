<?php

namespace App\Mcp\Resources;

use App\Growth\Manifest\ManifestExporter;
use App\Mcp\Resources\Concerns\ReturnsStructuredJson;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\MimeType;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Contracts\HasUriTemplate;
use Laravel\Mcp\Server\Resource;
use Laravel\Mcp\Support\UriTemplate;

#[Name('Manifest TOC')]
#[Description('Bounded table of contents for a project manifest: per-section row counts plus the URIs to fetch each slice. Use to size a fetch plan before pulling a full manifest section.')]
#[MimeType('application/json')]
class ManifestResource extends Resource implements HasUriTemplate
{
    use ReturnsStructuredJson;

    public function __construct(private readonly ManifestExporter $exporter) {}

    public function uriTemplate(): UriTemplate
    {
        return new UriTemplate('growth://projects/{project}/manifest');
    }

    public function handle(Request $request): Response
    {
        $projectId = $request->get('project');

        try {
            return $this->json($this->exporter->tableOfContents($projectId));
        } catch (ModelNotFoundException) {
            return Response::error("Project [{$projectId}] not found.");
        }
    }
}
