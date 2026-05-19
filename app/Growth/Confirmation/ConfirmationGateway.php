<?php

namespace App\Growth\Confirmation;

/**
 * Asks the caller to confirm an irreversible action before it proceeds.
 *
 * Implementations are MCP-agnostic by design: a service depends only on this
 * contract, never on the transport that ultimately collects the answer. The
 * MCP layer supplies an implementation that elicits the confirmation from the
 * connected client; everywhere else {@see NullConfirmationGateway} returns
 * `null`.
 *
 * A `null` return always means "no confirmation available" — an unsupported
 * client, a client error, or the no-op gateway — so callers can fall back to
 * a different guard rather than guessing the user's intent.
 */
interface ConfirmationGateway
{
    /**
     * Ask the caller to confirm an action.
     *
     * @param  string  $message  The prompt describing what will happen.
     * @return bool|null `true` when confirmed, `false` when declined or
     *                   cancelled, `null` when no confirmation is available.
     */
    public function confirm(string $message): ?bool;
}
