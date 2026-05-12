<?php

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

test('horizon dashboard route is registered', function () {
    expect(Route::has('horizon.index'))->toBeTrue();
});

test('horizon access is allowed for configured emails', function () {
    config(['horizon.allowed_emails' => ['allowed@example.com']]);

    $user = User::factory()->create(['email' => 'allowed@example.com']);

    expect(Gate::forUser($user)->allows('viewHorizon'))->toBeTrue();
});

test('horizon access is denied for unconfigured emails', function () {
    config(['horizon.allowed_emails' => ['allowed@example.com']]);

    $user = User::factory()->create(['email' => 'other@example.com']);

    expect(Gate::forUser($user)->allows('viewHorizon'))->toBeFalse();
});
