<?php

namespace App\Mcp\Tools\Manifest;

use App\Growth\Manifest\ManifestExporter;
use App\Mcp\McpLogReporter;
use App\Mcp\McpProgressReporter;
use Generator;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use InvalidArgumentException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Logging\Logging;
use Laravel\Mcp\Server\Notifications\ProgressNotification;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('Export a Growth project as a manifest. Without `sections`, returns a bounded table of contents (project metadata + per-section counts + resource URIs for streaming each slice). Pass `sections: ["*"]` for the full round-trip manifest, or a subset like `["requirements","plan"]` to fetch only those slices. The full manifest is also available as the `growth://projects/{id}/manifest` resource (TOC) and `growth://projects/{id}/manifest/{section}` resources (per slice). Output uses deterministic ordering and stable slugs so two exports of the same project produce byte-identical JSON; each entity carries an `_exported_at` timestamp that lets `apply-manifest` detect post-export drift on re-apply.')]
class ExportManifest extends Tool
{
    public function __construct(private readonly ManifestExporter $exporter) {}

    public function handle(Request $request, ProgressNotification $progress, Logging $logging): Generator
    {
        $request->validate([
            'project_id' => 'required|string|owned_project',
            'sections' => 'sometimes|array',
            'sections.*' => 'string',
        ]);

        $projectId = $request->get('project_id');
        $sections = $request->get('sections');

        if ($sections === null) {
            yield Response::structured([
                'mode' => 'toc',
                'toc' => $this->exporter->tableOfContents($projectId),
            ]);

            return;
        }

        $reporter = McpProgressReporter::forRequest($request, $progress);
        $log = new McpLogReporter($logging);

        try {
            $manifest = $this->exporter->export($projectId, $sections, $reporter, $log);
        } catch (InvalidArgumentException $e) {
            yield Response::error($e->getMessage());

            return;
        }

        yield Response::structured([
            'mode' => 'manifest',
            'manifest' => $manifest,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'project_id' => $schema->string()
                ->description('ULID of the project to export.')
                ->required(),
            'sections' => $schema->array()
                ->description('Optional. Omit for a bounded TOC response (project metadata + section counts). Pass `["*"]` for the full manifest, or a subset of '.implode(', ', ManifestExporter::SECTIONS).'.')
                ->items($schema->string()),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'mode' => $schema->string()
                ->description('`toc` when `sections` is omitted; `manifest` when sections were requested.')
                ->required(),
            'toc' => $schema->object()
                ->description('Bounded table of contents. Present when `mode` is `toc`.'),
            'manifest' => $schema->object()
                ->description('The exported manifest, ready to round-trip through `apply-manifest`. Present when `mode` is `manifest`.'),
        ];
    }
}
