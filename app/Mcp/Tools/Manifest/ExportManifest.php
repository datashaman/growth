<?php

namespace App\Mcp\Tools\Manifest;

use App\Growth\Manifest\ManifestExporter;
use App\Mcp\McpProgressReporter;
use Generator;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Notifications\ProgressNotification;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('Export a Growth project as a manifest (project + stakeholders + concerns + requirements + architecture + plan + verification). Output uses deterministic ordering and stable slugs so two exports of the same project produce byte-identical JSON; each entity carries an `_exported_at` timestamp that lets `apply-manifest` detect post-export drift on re-apply.')]
class ExportManifest extends Tool
{
    public function __construct(private readonly ManifestExporter $exporter) {}

    public function handle(Request $request, ProgressNotification $progress): Generator
    {
        $request->validate([
            'project_id' => 'required|string|owned_project',
        ]);

        $reporter = McpProgressReporter::forRequest($request, $progress);

        $manifest = $this->exporter->export($request->get('project_id'), $reporter);

        yield Response::structured(['manifest' => $manifest]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'project_id' => $schema->string()
                ->description('ULID of the project to export.')
                ->required(),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'manifest' => $schema->object()->description('The exported manifest. Ready to round-trip back through `apply-manifest`.')->required(),
        ];
    }
}
