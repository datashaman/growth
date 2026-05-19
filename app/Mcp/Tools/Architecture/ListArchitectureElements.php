<?php

namespace App\Mcp\Tools\Architecture;

use App\Models\DesignElement;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('List architecture elements in one architecture view.')]
class ListArchitectureElements extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'design_view_id' => 'required|string|owned_design_view',
            'kind' => 'nullable|in:entity,relationship,attribute,constraint',
            'q' => 'nullable|string|max:255',
        ]);

        $query = DesignElement::query()->where('design_view_id', $data['design_view_id']);
        if (isset($data['kind'])) {
            $query->where('kind', $data['kind']);
        }
        if (isset($data['q'])) {
            $query->where('name', 'like', '%'.$data['q'].'%');
        }

        return Response::structured([
            'results' => $query->orderBy('kind')->orderBy('name')->get()->map(fn ($element) => [
                'id' => $element->id,
                'kind' => $element->kind,
                'name' => $element->name,
                'type' => $element->type,
                'purpose' => $element->purpose,
                'properties' => $element->properties,
            ])->all(),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'design_view_id' => $schema->string()->description('Architecture view ULID')->required(),
            'kind' => $schema->string()->description('Filter by element kind')->enum(['entity', 'relationship', 'attribute', 'constraint']),
            'q' => $schema->string()->description('Substring match on element name'),
        ];
    }
}
