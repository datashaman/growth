<?php

namespace App\Mcp\Resources;

use App\Growth\Manifest\ManifestExporter;
use App\Mcp\Resources\Concerns\ReturnsStructuredJson;
use App\Models\Project;
use InvalidArgumentException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\MimeType;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Contracts\HasUriTemplate;
use Laravel\Mcp\Server\Resource;
use Laravel\Mcp\Support\UriTemplate;

#[Name('Manifest Section')]
#[Description('A single slice of a project manifest (stakeholders, concerns, requirements, architecture, plan, or verification). Streams one section at a time so a mid-size project fits in the MCP token budget.')]
#[MimeType('application/json')]
class ManifestSectionResource extends Resource implements HasUriTemplate
{
    use ReturnsStructuredJson;

    public function __construct(private readonly ManifestExporter $exporter) {}

    public function uriTemplate(): UriTemplate
    {
        return new UriTemplate('growth://projects/{project}/manifest/{section}');
    }

    public function handle(Request $request): Response
    {
        $projectId = $request->get('project');
        $section = $request->get('section');

        if (! Project::query()->whereKey($projectId)->exists()) {
            return Response::error("Project [{$projectId}] not found.");
        }

        try {
            $manifest = $this->exporter->export($projectId, [$section]);
        } catch (InvalidArgumentException $e) {
            return Response::error($e->getMessage());
        }

        return $this->json([
            'project' => $manifest['project'],
            'section' => $section,
            'data' => $manifest[$section] ?? null,
        ]);
    }
}
