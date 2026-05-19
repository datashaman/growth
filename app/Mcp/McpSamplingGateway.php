<?php

namespace App\Mcp;

use App\Growth\Sampling\NullSamplingGateway;
use App\Growth\Sampling\SamplingGateway;
use Laravel\Mcp\Exceptions\JsonRpcException;
use Laravel\Mcp\Sampling\Message;
use Laravel\Mcp\Server\Sampling\Sampling;

/**
 * {@see SamplingGateway} that requests a completion from the connected MCP
 * client via `sampling/createMessage`.
 *
 * The round-trip is gated by the client: {@see Sampling::createMessage()}
 * throws when the client never declared the `sampling` capability, and the
 * client may itself reject the request. Either way this gateway returns
 * `null`, so a tool's body is identical whether or not sampling is available
 * — the same graceful-degradation contract as {@see NullSamplingGateway}.
 */
class McpSamplingGateway implements SamplingGateway
{
    public function __construct(private readonly Sampling $sampling) {}

    public function requestText(string $prompt, int $maxTokens, ?string $systemPrompt = null): ?string
    {
        try {
            $result = $this->sampling->createMessage(
                messages: [Message::user($prompt)],
                maxTokens: $maxTokens,
                systemPrompt: $systemPrompt,
            );
        } catch (JsonRpcException) {
            return null;
        }

        $text = $result->text();

        return $text !== null && trim($text) !== '' ? $text : null;
    }
}
