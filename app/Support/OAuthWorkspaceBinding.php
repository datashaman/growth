<?php

namespace App\Support;

/**
 * Request-scoped carrier for the workspace a token should be bound to as it
 * moves through an OAuth token exchange (#197, #214).
 *
 * The authorize and token exchanges are two separate HTTP requests with no
 * shared session — the consent POST is browser-borne, the token POST is a
 * back-channel call from the MCP client. The binding therefore cannot ride the
 * session: it rides the persisted grant rows instead. The consent screen
 * writes the chosen workspace onto the auth code; each token exchange reads it
 * off the grant it consumes (the auth code, or the old refresh token) and
 * writes it onto the issued token.
 *
 * This holder bridges that read and write within a single token-exchange
 * request — the league grant validates the consumed grant (where we capture
 * the workspace) before it persists the new token (where we apply it).
 */
class OAuthWorkspaceBinding
{
    private ?string $workspaceId = null;

    public function set(?string $workspaceId): void
    {
        $this->workspaceId = $workspaceId !== '' ? $workspaceId : null;
    }

    public function get(): ?string
    {
        return $this->workspaceId;
    }
}
