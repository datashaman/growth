<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Passport\Client;

test('login page is available', function () {
    $this->get('/login')
        ->assertOk()
        ->assertSee('Sign in');
});

test('users can authenticate with email and password', function () {
    $user = User::factory()->create([
        'email' => 'alice@example.com',
        'password' => Hash::make('secret-password'),
    ]);

    $this->post('/login', [
        'email' => 'alice@example.com',
        'password' => 'secret-password',
    ])->assertRedirect('/');

    $this->assertAuthenticatedAs($user);
});

test('invalid credentials are rejected', function () {
    User::factory()->create([
        'email' => 'alice@example.com',
        'password' => Hash::make('secret-password'),
    ]);

    $this->from('/login')->post('/login', [
        'email' => 'alice@example.com',
        'password' => 'wrong-password',
    ])->assertRedirect('/login')
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('oauth authorization redirects guests to login', function () {
    $client = Client::factory()->asPublic()->create([
        'name' => 'MCP Client',
        'redirect_uris' => ['https://client.example/callback'],
    ]);
    $verifier = Str::random(64);
    $challenge = strtr(rtrim(base64_encode(hash('sha256', $verifier, true)), '='), '+/', '-_');
    $query = http_build_query([
        'client_id' => $client->id,
        'redirect_uri' => 'https://client.example/callback',
        'response_type' => 'code',
        'scope' => 'mcp:use',
        'state' => 'test-state',
        'code_challenge' => $challenge,
        'code_challenge_method' => 'S256',
    ]);

    $this->get("/oauth/authorize?{$query}")
        ->assertRedirect('/login');
});
