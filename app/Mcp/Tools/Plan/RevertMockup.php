<?php

namespace App\Mcp\Tools\Plan;

use App\Models\SpecMockup;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description("Revert a spec mockup to an earlier revision. The chosen revision's HTML is appended as a new latest revision — history is never discarded.")]
class RevertMockup extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'mockup_id' => 'required|string|owned_mockup',
            'revision' => 'required|integer|min:1',
        ]);

        $mockup = SpecMockup::findOrFail($data['mockup_id']);

        $target = $mockup->revisions()->where('number', $data['revision'])->first();

        if ($target === null) {
            return new ResponseFactory(Response::error(
                sprintf('Mockup has no revision %d.', $data['revision']),
            ));
        }

        $appended = $mockup->appendRevision($target->html);

        return Response::structured([
            'mockup_id' => $mockup->id,
            'reverted_to' => $target->number,
            'revision' => $appended->number,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'mockup_id' => $schema->string()->description('Spec mockup ULID to revert')->required(),
            'revision' => $schema->integer()->description('Number of the revision to revert to')->required(),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'mockup_id' => $schema->string()->required(),
            'reverted_to' => $schema->integer()->description('The revision number reverted to')->required(),
            'revision' => $schema->integer()->description('Number of the new latest revision this created')->required(),
        ];
    }
}
