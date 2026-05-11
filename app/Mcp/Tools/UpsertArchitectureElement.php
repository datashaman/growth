<?php

namespace App\Mcp\Tools;

use App\Models\DesignElement;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Create or update an architecture element inside an architecture view.')]
class UpsertArchitectureElement extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'id' => 'nullable|string|owned_design_element',
            'design_view_id' => 'required|string|owned_design_view',
            'kind' => 'required|in:entity,relationship,attribute,constraint',
            'name' => 'required|string|max:255',
            'type' => 'nullable|string|max:100',
            'purpose' => 'nullable|string',
            'properties' => 'nullable|array',
        ]);

        $id = $data['id'] ?? null;
        unset($data['id']);

        $element = $id
            ? tap(DesignElement::findOrFail($id))->update($data)
            : DesignElement::create($data);

        return Response::structured([
            'id' => $element->id,
            'design_view_id' => $element->design_view_id,
            'kind' => $element->kind,
            'name' => $element->name,
            'created' => $element->wasRecentlyCreated,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->description('Existing architecture element ULID. Omit to create.'),
            'design_view_id' => $schema->string()->description('Parent architecture view ULID')->required(),
            'kind' => $schema->string()->description('Element kind')->enum(['entity', 'relationship', 'attribute', 'constraint'])->required(),
            'name' => $schema->string()->description('Element name')->required(),
            'type' => $schema->string()->description('Element type within the viewpoint vocabulary'),
            'purpose' => $schema->string()->description('Why this element exists'),
            'properties' => $schema->object()->description('Kind-specific structured data'),
        ];
    }
}
