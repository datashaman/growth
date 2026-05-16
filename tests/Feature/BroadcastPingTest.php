<?php

use App\Events\Ping;
use App\Models\User;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Support\Facades\Event;

test('broadcast:ping dispatches Ping on the user private channel', function () {
    Event::fake([Ping::class]);

    $user = User::factory()->create();

    $this->artisan('broadcast:ping', ['user' => $user->email])
        ->assertSuccessful();

    Event::assertDispatched(Ping::class, function (Ping $event) use ($user) {
        $channels = $event->broadcastOn();

        return $event->user->is($user)
            && count($channels) === 1
            && $channels[0] instanceof PrivateChannel
            && $channels[0]->name === 'private-App.Models.User.'.$user->id;
    });
});

test('broadcast:ping fails for unknown user', function () {
    $this->artisan('broadcast:ping', ['user' => 'nobody@example.com'])
        ->assertFailed();
});
