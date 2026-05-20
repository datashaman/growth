<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Browser-based MCP clients (e.g. MCP Inspector) need preflight and
    | response CORS headers for the OAuth discovery, OAuth authorization,
    | and MCP transport endpoints in addition to the framework defaults.
    |
    */

    'paths' => [
        'api/*',
        'sanctum/csrf-cookie',
        'mcp/*',
        '.well-known/*',
        'oauth/*',
    ],

    'allowed_methods' => ['*'],

    'allowed_origins' => ['*'],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => ['Mcp-Session-Id'],

    'max_age' => 0,

    'supports_credentials' => false,

];
