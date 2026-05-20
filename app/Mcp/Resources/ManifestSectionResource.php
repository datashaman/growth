<?php

namespace App\Mcp\Resources;

use App\Growth\Manifest\ManifestExporter;
use App\Mcp\Resources\Concerns\ReturnsStructuredJson;
use Illuminate\Database\Eloquent\ModelNotFoundException;
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

        try {
            $manifest = $this->exporter->export($projectId, [$section]);
        } catch (ModelNotFoundException) {
            return Response::error("Project [{$projectId}] not found.");
        } catch (InvalidArgumentException $e) {
            return Response::error($e->getMessage());
        }

        return $this->json([
            'project' => $manifest['project'],
            'section' => $section,
            // Sections with zero rows are omitted from the round-trip shape;
            // here a client asked for the slice explicitly, so we return an
            // empty array rather than null and let them iterate uniformly.
            'data' => $manifest[$section] ?? [],
        ]);
    }
}
