<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('user:token {email : Email address of the user} {--name=local : Token label} {--workspace= : Workspace slug or ULID to bind the token to (defaults to the user\'s active workspace)}')]
#[Description('Issue a Passport OAuth personal access token for a user, bound to a workspace. Bearer tokens are used for HTTP MCP; local stdio should prefer GROWTH_USER_EMAIL or GROWTH_USER_ID (plus optional GROWTH_WORKSPACE_ID).')]
class UserToken extends Command
{
    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->first();

        if (! $user) {
            $this->error("No user found with email [{$this->argument('email')}].");

            return self::FAILURE;
        }

        $workspaceId = $this->resolveWorkspaceId($user);
        if ($workspaceId === null) {
            return self::FAILURE;
        }

        $name = $this->option('name');
        $result = $user->createToken($name, ['mcp:use']);
        $result->getToken()?->forceFill(['workspace_id' => $workspaceId])->save();

        $this->info("Token issued for {$user->email} (label: {$name}, workspace: {$workspaceId}).");
        $this->line('');
        $this->line($result->accessToken);
        $this->line('');
        $this->comment('Store this — it cannot be retrieved again. Use it as an HTTP bearer token.');
        $this->comment('For local stdio MCP, prefer GROWTH_USER_EMAIL or GROWTH_USER_ID (+ GROWTH_WORKSPACE_ID).');

        return self::SUCCESS;
    }

    private function resolveWorkspaceId(User $user): ?string
    {
        $option = $this->option('workspace');

        if ($option === null || $option === '') {
            if ($user->active_workspace_id === null) {
                $this->error("User [{$user->email}] has no active workspace; pass --workspace=<slug|ulid>.");

                return null;
            }

            return $user->active_workspace_id;
        }

        $workspace = $user->workspaces()
            ->where(fn ($q) => $q->where('workspaces.id', $option)->orWhere('workspaces.slug', $option))
            ->first();

        if ($workspace === null) {
            $this->error("Workspace [{$option}] is not accessible to {$user->email}.");

            return null;
        }

        return $workspace->id;
    }
}
