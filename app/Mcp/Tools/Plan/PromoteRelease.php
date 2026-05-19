<?php

namespace App\Mcp\Tools\Plan;

use App\Growth\Transitions\IllegalTransitionException;
use App\Growth\Transitions\PromoteRelease as PromoteReleaseTransition;
use App\Models\Release;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[IsDestructive(false)]
#[Description('Promote a release to a release candidate: move it from planned to candidate. Rejects any other source status with a clear message. Records a status transition with the acting user and timestamp.')]
class PromoteRelease extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'release_id' => 'required|string|owned_release',
            'reason' => 'nullable|string|max:1000',
        ]);

        $release = Release::findOrFail($data['release_id']);

        try {
            $transition = (new PromoteReleaseTransition)->apply($release, auth()->user(), $data['reason'] ?? null);
        } catch (IllegalTransitionException $e) {
            return new ResponseFactory(Response::error($e->getMessage()));
        }

        return Response::structured([
            'release_id' => $release->id,
            'from_status' => $transition->from_status,
            'to_status' => $transition->to_status,
            'transition_id' => $transition->id,
            'transitioned_at' => $transition->transitioned_at->toIso8601String(),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'release_id' => $schema->string()->description('Release ULID')->required(),
            'reason' => $schema->string()->description('Optional note recorded with the transition'),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'release_id' => $schema->string()->required(),
            'from_status' => $schema->string()->required(),
            'to_status' => $schema->string()->required(),
            'transition_id' => $schema->string()->required(),
            'transitioned_at' => $schema->string()->required(),
        ];
    }
}
