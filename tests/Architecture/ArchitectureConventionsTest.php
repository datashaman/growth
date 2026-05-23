<?php

use Laravel\Mcp\Server\Tool;

arch('models do not depend on the MCP layer')
    ->expect('App\Models')
    ->classes()
    ->not->toUse('App\Mcp');

arch('MCP tools are concrete Tool classes')
    ->expect('App\Mcp\Tools')
    ->classes()
    ->toExtend(Tool::class)
    ->ignoring([
        'App\Mcp\Tools\Plan\ResolvesMockupOwner',
        'App\Mcp\Tools\Reviews\Concerns',
        'App\Mcp\Tools\Verification\Concerns',
    ]);
