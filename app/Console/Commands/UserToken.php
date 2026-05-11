<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('user:token {email : Email address of the user} {--name=local : Token label}')]
#[Description('Issue a Sanctum personal access token for a user. Use bearer tokens for HTTP MCP; local stdio should prefer GROWTH_USER_EMAIL or GROWTH_USER_ID.')]
class UserToken extends Command
{
    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->first();

        if (! $user) {
            $this->error("No user found with email [{$this->argument('email')}].");

            return self::FAILURE;
        }

        $name = $this->option('name');
        $token = $user->createToken($name)->plainTextToken;

        $this->info("Token issued for {$user->email} (label: {$name}).");
        $this->line('');
        $this->line($token);
        $this->line('');
        $this->comment('Store this — it cannot be retrieved again. Use it as an HTTP bearer token.');
        $this->comment('For local stdio MCP, prefer GROWTH_USER_EMAIL or GROWTH_USER_ID instead.');

        return self::SUCCESS;
    }
}
