<?php

namespace App\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Report the authenticated user for this session, plus the transport in use. Returns `{authenticated: false}` for anonymous local sessions without GROWTH_USER_EMAIL or GROWTH_USER_ID. Useful for verifying auth state and which user the model owner-scopes will filter against.')]
class WhoAmI extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $user = auth()->user();

        if ($user === null) {
            return Response::structured([
                'authenticated' => false,
                'user_id' => null,
                'email' => null,
                'name' => null,
            ]);
        }

        return Response::structured([
            'authenticated' => true,
            'user_id' => $user->id,
            'email' => $user->email,
            'name' => $user->name,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'authenticated' => $schema->boolean()->required(),
            'user_id' => $schema->integer(),
            'email' => $schema->string(),
            'name' => $schema->string(),
        ];
    }
}
