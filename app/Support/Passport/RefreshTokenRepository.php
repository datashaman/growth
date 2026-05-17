<?php

namespace App\Support\Passport;

use App\Support\OAuthWorkspaceBinding;
use Laravel\Passport\Bridge\RefreshTokenRepository as BaseRefreshTokenRepository;
use Laravel\Passport\Passport;
use League\OAuth2\Server\Entities\RefreshTokenEntityInterface;

/**
 * Carries the workspace binding across the refresh-token grant (#197, #214).
 *
 * The refresh token stores its own copy of the binding so that exchanging it
 * for a new token does not lose the workspace: validating the old refresh
 * token replays the binding onto the {@see OAuthWorkspaceBinding} holder, and
 * the new access and refresh tokens both inherit it from there.
 */
class RefreshTokenRepository extends BaseRefreshTokenRepository
{
    public function persistNewRefreshToken(RefreshTokenEntityInterface $refreshTokenEntity): void
    {
        parent::persistNewRefreshToken($refreshTokenEntity);

        $workspaceId = app(OAuthWorkspaceBinding::class)->get();

        if ($workspaceId !== null) {
            Passport::refreshToken()->newQuery()
                ->whereKey($refreshTokenEntity->getIdentifier())
                ->update(['workspace_id' => $workspaceId]);
        }
    }

    /**
     * Capture the old refresh token's workspace binding as it is validated
     * during a refresh-token exchange, so the reissued tokens inherit it.
     */
    public function isRefreshTokenRevoked(string $tokenId): bool
    {
        $workspaceId = Passport::refreshToken()->newQuery()
            ->whereKey($tokenId)
            ->value('workspace_id');

        if (is_string($workspaceId) && $workspaceId !== '') {
            app(OAuthWorkspaceBinding::class)->set($workspaceId);
        }

        return parent::isRefreshTokenRevoked($tokenId);
    }
}
