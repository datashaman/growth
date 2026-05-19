<?php

namespace App\Mcp\Tools\Stakeholders;

use App\Models\Stakeholder;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[IsDestructive]
#[Description('Delete a stakeholder. Concerns raised by this stakeholder have their raised_by_stakeholder_id set to null; no concerns are deleted.')]
class DeleteStakeholder extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'id' => 'required|string|owned_stakeholder',
        ]);

        $stakeholder = Stakeholder::findOrFail($data['id']);
        $orphanedConcerns = $stakeholder->concerns()->count();
        $stakeholder->delete();

        return Response::structured([
            'id' => $data['id'],
            'deleted' => true,
            'concerns_orphaned' => $orphanedConcerns,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()
                ->description('Stakeholder ULID to delete')
                ->required(),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->required(),
            'deleted' => $schema->boolean()->required(),
            'concerns_orphaned' => $schema->integer()->required(),
        ];
    }
}
