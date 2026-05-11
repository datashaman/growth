<?php

namespace App\Mcp\Tools;

use App\Models\Source;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Create or update an input source such as a brief, interview, ticket, email, document, or prototype URL.')]
class UpsertSource extends Tool
{
    private const KINDS = [
        'brief', 'rfp', 'interview', 'transcript', 'contract',
        'ticket', 'email', 'doc', 'prototype', 'reference', 'other',
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
            'id' => $schema->string()->description('Existing source ULID. Omit to create.'),
            'project_id' => $schema->string()->description('Project ULID')->required(),
            'kind' => $schema->string()->description('Source type')->enum(self::KINDS)->required(),
            'title' => $schema->string()->description('Short human label')->required(),
            'body' => $schema->string()->description('Pasted source text. Omit for URL-only sources.'),
            'uri' => $schema->string()->description('Canonical source URL'),
            'external_ref' => $schema->string()->description('External identifier such as a ticket id or message id'),
        ];
    }
}
