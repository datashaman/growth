<?php

namespace App\Mcp\Tools\Sources;

use App\Models\Source;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[IsDestructive(false)]
#[Description('Create or update a Source: an input artifact the project definition is derived from (brief, RFP, interview transcript, contract, design prototype URL, etc.). Sources are then cited by project artifacts via cite-artifact.')]
class UpsertSource extends Tool
{
    private const KINDS = [
        'brief', 'rfp', 'interview', 'transcript', 'contract',
        'source', 'ticket', 'email', 'doc', 'prototype', 'other',
    ];

    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'id' => 'nullable|string|owned_source',
            'project_id' => 'required|string|owned_project',
            'kind' => 'required|in:'.implode(',', self::KINDS),
            'title' => 'required|string|max:255',
            'body' => 'nullable|string',
            'uri' => 'nullable|string|max:2048',
            'external_ref' => 'nullable|string|max:255',
        ]);

        $id = $data['id'] ?? null;
        unset($data['id']);

        $source = $id
            ? tap(Source::findOrFail($id))->update($data)
            : Source::create($data);

        return Response::structured([
            'id' => $source->id,
            'kind' => $source->kind,
            'title' => $source->title,
            'created' => $source->wasRecentlyCreated,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()
                ->description('Existing source ULID. Omit to create.'),
            'project_id' => $schema->string()
                ->description('Project ULID')
                ->required(),
            'kind' => $schema->string()
                ->description('Type of source. `prototype` covers Figma/Stitch/Sketch URLs.')
                ->enum(self::KINDS)
                ->required(),
            'title' => $schema->string()
                ->description('Short human label (e.g. "Q2 kickoff brief", "RFP operations section")')
                ->required(),
            'body' => $schema->string()
                ->description('Pasted text content (brief copy, transcript). Omit for URL-only sources.'),
            'uri' => $schema->string()
                ->description('Canonical URL — Figma/Stitch/Sketch/Notion/ticket/etc.'),
            'external_ref' => $schema->string()
                ->description('External identifier — RFP doc number, Linear ticket id, email message-id'),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->required(),
            'kind' => $schema->string()->required(),
            'title' => $schema->string()->required(),
            'created' => $schema->boolean()->required(),
        ];
    }
}
