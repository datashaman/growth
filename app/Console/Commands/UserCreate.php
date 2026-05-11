<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

#[Signature('user:create {email : Email address of the user} {--name= : Display name} {--password= : Password; random if omitted}')]
#[Description('Create a local application user for trusted MCP sessions or web authentication.')]
class UserCreate extends Command
{
    public function handle(): int
    {
        $email = (string) $this->argument('email');

        if (User::where('email', $email)->exists()) {
            $this->error("User already exists with email [{$email}].");

            return self::FAILURE;
        }

        $name = (string) ($this->option('name') ?: Str::before($email, '@'));
        $password = (string) ($this->option('password') ?: Str::password(32));

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => $password,
        ]);

        $this->info("User created for {$user->email} (id: {$user->id}).");
        $this->line('');
        $this->comment('Use this for trusted local MCP stdio sessions:');
        $this->comment("  GROWTH_USER_EMAIL='{$user->email}' php artisan mcp:start intake");

        return self::SUCCESS;
    }
}
