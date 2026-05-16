<?php

namespace App\Console\Commands;

use App\Events\Ping;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('broadcast:ping {user : Email or id of the user to ping} {--message=pong : Payload message}')]
#[Description('Broadcast a Ping event to the given user on their private channel. Proof-of-life for the broadcasting stack.')]
class BroadcastPing extends Command
{
    public function handle(): int
    {
        $argument = (string) $this->argument('user');

        $user = is_numeric($argument)
            ? User::find((int) $argument)
            : User::where('email', $argument)->first();

        if ($user === null) {
            $this->error("User not found: {$argument}");

            return self::FAILURE;
        }

        Ping::dispatch($user, (string) $this->option('message'));

        $this->info("Pinged {$user->email} on App.Models.User.{$user->id}");

        return self::SUCCESS;
    }
}
