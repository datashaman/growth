<?php

namespace App\Mcp\Servers\Concerns;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Laravel\Mcp\Server\Transport\StdioTransport;

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

        fwrite(STDERR, '[mcp-server] GROWTH_TOKEN is no longer used for local MCP auth; use GROWTH_USER_EMAIL or GROWTH_USER_ID.'.PHP_EOL);

        return null;
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
