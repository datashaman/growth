<?php

namespace App\Support\Passport;

use App\Models\WorkspaceMembership;
use App\Support\OAuthWorkspaceBinding;
use Laravel\Passport\Bridge\AuthCodeRepository as BaseAuthCodeRepository;
use Laravel\Passport\Passport;
use League\OAuth2\Server\Entities\AuthCodeEntityInterface;

/**
 * Persists the workspace the user picked on the OAuth consent screen onto the
 * auth code, and replays it onto the {@see OAuthWorkspaceBinding} holder when
 * the auth code is later exchanged for a token (#197, #214).
 */
class AuthCodeRepository extends BaseAuthCodeRepository
{
    /**
     * Write the consent-screen workspace selection onto the new auth code.
     *
     * This runs inside the approve request, so the chosen `workspace_id` is
     * still on the request. It is honoured only when the authorizing user is a
     * member of that workspace — a tampered value falls back to an unbound
     * token rather than binding to a workspace the user cannot access.
     */
    public function persistNewAuthCode(AuthCodeEntityInterface $authCodeEntity): void
    {
        parent::persistNewAuthCode($authCodeEntity);

        $workspaceId = $this->consentWorkspaceId($authCodeEntity->getUserIdentifier());

        if ($workspaceId !== null) {
            Passport::authCode()->newQuery()
                ->whereKey($authCodeEntity->getIdentifier())
                ->update(['workspace_id' => $workspaceId]);
        }
    }

    /**
     * Capture the auth code's workspace binding as it is validated during a
     * token exchange, so the access and refresh tokens can inherit it.
     */
    public function isAuthCodeRevoked(string $codeId): bool
    {
        $workspaceId = Passport::authCode()->newQuery()
            ->whereKey($codeId)
            ->value('workspace_id');

        if (is_string($workspaceId) && $workspaceId !== '') {
            app(OAuthWorkspaceBinding::class)->set($workspaceId);
        }

        return parent::isAuthCodeRevoked($codeId);
    }

    private function consentWorkspaceId(?string $userId): ?string
    {
        $workspaceId = request()->input('workspace_id');

        if (! is_string($workspaceId) || $workspaceId === '' || $userId === null) {
            return null;
        }

        $isMember = WorkspaceMembership::query()
            ->where('user_id', $userId)
            ->where('workspace_id', $workspaceId)
            ->exists();

        return $isMember ? $workspaceId : null;
    }
}
