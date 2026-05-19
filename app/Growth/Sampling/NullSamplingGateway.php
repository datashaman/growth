<?php

namespace App\Growth\Sampling;

/**
 * Default {@see SamplingGateway} that never produces a completion.
 *
 * Services accept this as their default so callers that cannot sample — the
 * webapp, tests, MCP callers whose client did not declare the `sampling`
 * capability — need no special-casing and simply proceed without one.
 */
class NullSamplingGateway implements SamplingGateway
{
    public function requestText(string $prompt, int $maxTokens, ?string $systemPrompt = null): ?string
    {
        return null;
    }
}
