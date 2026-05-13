<?php

namespace App\Mcp\Tools;

use App\Models\DesignElement;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Throwable;

#[Description('Create or update one or more architecture elements inside architecture views. Items run independently; per-item failures do not abort the batch.')]
class UpsertArchitectureElements extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $payload = $request->validate([
            'items' => 'required|array|min:1',
        ]);

        $results = [];
        foreach ($payload['items'] as $index => $item) {
            $results[] = $this->upsertItem((int) $index, is_array($item) ? $item : []);
        }

        return Response::structured(['items' => $results]);
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function upsertItem(int $index, array $item): array
    {
        try {
            $data = Validator::make($item, $this->itemRules())->validate();
        } catch (ValidationException $e) {
            return [
                'index' => $index,
                'ok' => false,
                'errors' => $e->errors(),
            ];
        }

        try {
            $id = $data['id'] ?? null;
            unset($data['id']);

            $element = $id
                ? tap(DesignElement::findOrFail($id))->update($data)
                : DesignElement::create($data);

            return [
                'index' => $index,
                'ok' => true,
                'id' => $element->id,
                'design_view_id' => $element->design_view_id,
                'kind' => $element->kind,
                'name' => $element->name,
                'created' => $element->wasRecentlyCreated,
            ];
        } catch (Throwable $e) {
            return [
                'index' => $index,
                'ok' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * @return array<string, string>
     */
    private function itemRules(): array
    {
        return [
            'id' => 'nullable|string|owned_design_element',
            'design_view_id' => 'required|string|owned_design_view',
            'kind' => 'required|in:entity,relationship,attribute,constraint',
            'name' => 'required|string|max:255',
            'type' => 'nullable|string|max:100',
            'purpose' => 'nullable|string',
            'properties' => 'nullable|array',
        ];
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'items' => $schema->array()
                ->items($schema->object(fn (JsonSchema $s) => [
                    'id' => $s->string()->description('Existing architecture element ULID. Omit to create.'),
                    'design_view_id' => $s->string()->description('Parent architecture view ULID')->required(),
                    'kind' => $s->string()->description('Element kind')->enum(['entity', 'relationship', 'attribute', 'constraint'])->required(),
                    'name' => $s->string()->description('Element name')->required(),
                    'type' => $s->string()->description('Element type within the viewpoint vocabulary'),
                    'purpose' => $s->string()->description('Why this element exists'),
                    'properties' => $s->object()->description('Kind-specific structured data'),
                ]))
                ->min(1)
                ->description('One or more architecture elements to create or update. Items are processed independently; per-item failures are reported in the response without aborting the batch.')
                ->required(),
        ];
    }
}
