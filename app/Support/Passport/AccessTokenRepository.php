<?php

namespace App\Support\Passport;

use App\Support\OAuthWorkspaceBinding;
use Laravel\Passport\Bridge\AccessTokenRepository as BaseAccessTokenRepository;
use Laravel\Passport\Passport;
use League\OAuth2\Server\Entities\AccessTokenEntityInterface;

/**
 * Binds a freshly issued access token to the workspace captured from the grant
 * it was minted from (#197, #214). The {@see OAuthWorkspaceBinding} holder is
 * populated earlier in the same token-exchange request — by the auth code or
 * refresh token repository, depending on the grant type.
 */
class AccessTokenRepository extends BaseAccessTokenRepository
{
    public function persistNewAccessToken(AccessTokenEntityInterface $accessTokenEntity): void
    {
        parent::persistNewAccessToken($accessTokenEntity);

        $workspaceId = app(OAuthWorkspaceBinding::class)->get();

        if ($workspaceId !== null) {
            Passport::token()->newQuery()
                ->whereKey($accessTokenEntity->getIdentifier())
                ->update(['workspace_id' => $workspaceId]);
        }
    }
}
