<?php

namespace App\Mcp\Servers\Concerns;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Laravel\Mcp\Server\Transport\StdioTransport;
use Laravel\Sanctum\PersonalAccessToken;

trait AuthenticatesLocalMcpSessions
{
    protected function bootTrustedLocalSession(): void
    {
        if (! $this->transport instanceof StdioTransport) {
            return;
        }

        $this->authenticateLocalSession(
            (string) env('GROWTH_TOKEN', ''),
            (string) env('GROWTH_USER_EMAIL', ''),
            (string) env('GROWTH_USER_ID', ''),
        );
    }

    public function authenticateLocalSession(
        string $token = '',
        string $userEmail = '',
        string $userId = '',
    ): ?int {
        if ($userId !== '') {
            return $this->authenticateLocalUser(User::find($userId), "GROWTH_USER_ID [{$userId}]");
        }

        if ($userEmail !== '') {
            return $this->authenticateLocalUser(User::where('email', $userEmail)->first(), "GROWTH_USER_EMAIL [{$userEmail}]");
        }

        if ($token === '') {
            return null;
        }

        $accessToken = PersonalAccessToken::findToken($token);

        if ($accessToken === null) {
            fwrite(STDERR, '[mcp-server] GROWTH_TOKEN does not match any personal access token; running unauthenticated.'.PHP_EOL);

            return null;
        }

        Auth::login($accessToken->tokenable);

        return $accessToken->tokenable_id;
    }

    private function authenticateLocalUser(?User $user, string $source): ?int
    {
        if ($user === null) {
            fwrite(STDERR, "[mcp-server] {$source} does not match any user; running unauthenticated.".PHP_EOL);

            return null;
        }

        Auth::login($user);

        return $user->id;
    }
}
