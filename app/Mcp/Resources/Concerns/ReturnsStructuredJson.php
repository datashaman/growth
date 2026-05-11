<?php

namespace App\Mcp\Resources\Concerns;

use Laravel\Mcp\Response;

trait ReturnsStructuredJson
{
    /**
     * @param  array<string, mixed>  $payload
     */
    protected function json(array $payload): Response
    {
        return Response::text(json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }
}
