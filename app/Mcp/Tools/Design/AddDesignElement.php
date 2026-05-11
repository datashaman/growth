<?php

namespace App\Mcp\Tools\Design;

use App\Models\DesignElement;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Add a design element to a view (architecture coverage rules). Kind is one of entity, relationship, attribute, constraint. Use the properties bag for kind-specific data (e.g. participants for a relationship).')]
class AddDesignElement extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'design_view_id' => 'required|string|owned_design_view',
            'kind' => 'required|in:entity,relationship,attribute,constraint',
            'name' => 'required|string|max:255',
            'type' => 'nullable|string|max:100',
            'purpose' => 'nullable|string',
            'properties' => 'nullable|array',
        ]);

        $element = DesignElement::create($data);

        return Response::structured([
            'id' => $element->id,
            'design_view_id' => $element->design_view_id,
            'kind' => $element->kind,
            'name' => $element->name,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'design_view_id' => $schema->string()
                ->description('Parent view ULID')
                ->required(),
            'kind' => $schema->string()
                ->description('Element kind per rule')
                ->enum(['entity', 'relationship', 'attribute', 'constraint'])
                ->required(),
            'name' => $schema->string()
                ->description('Element name')
                ->required(),
            'type' => $schema->string()
                ->description('Element type within the viewpoint vocabulary (e.g. "class", "subsystem", "uses")'),
            'purpose' => $schema->string()
                ->description('Why this element exists '),
            'properties' => $schema->object()
                ->description('Kind-specific data. For relationships: {"participants": [element_id_1, element_id_2]}. For attributes: {"owner_element_id": "..."}.'),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->required(),
            'design_view_id' => $schema->string()->required(),
            'kind' => $schema->string()->required(),
            'name' => $schema->string()->required(),
        ];
    }
}
