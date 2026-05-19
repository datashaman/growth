<?php

namespace App\Mcp\Tools\Architecture;

use App\Models\DesignElement;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[IsDestructive]
#[Description('Delete one architecture element.')]
class DeleteArchitectureElement extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate(['id' => 'required|string|owned_design_element']);
        DesignElement::findOrFail($data['id'])->delete();

        return Response::structured(['id' => $data['id'], 'deleted' => true]);
    }

    public function schema(JsonSchema $schema): array
    {
        return ['id' => $schema->string()->description('Architecture element ULID')->required()];
    }
}
