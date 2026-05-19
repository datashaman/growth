<?php

namespace App\Growth\Sampling;

/**
 * Obtains an LLM completion on behalf of a service.
 *
 * Implementations are MCP-agnostic by design: a service depends only on this
 * contract, never on the transport that ultimately produces the completion.
 * The MCP layer supplies an implementation that requests a sample from the
 * connected client; everywhere else {@see NullSamplingGateway} returns `null`.
 *
 * A `null` return always means "no completion available" — an unsupported
 * client, a client error, or the no-op gateway — so callers degrade
 * gracefully without distinguishing the cause.
 */
interface SamplingGateway
{
    /**
     * Request a text completion.
     *
     * @param  string  $prompt  The user prompt to complete.
     * @param  int  $maxTokens  Upper bound on the generated length.
     * @param  string|null  $systemPrompt  Optional system instruction.
     * @return string|null The generated text, or `null` when none is available.
     */
    public function requestText(string $prompt, int $maxTokens, ?string $systemPrompt = null): ?string;
}
