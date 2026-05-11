<?php

use App\Models\User;

it('rejects unauthenticated POSTs to the MCP HTTP route', function () {
    $this->postJson('/mcp/intake', [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/list',
    ])->assertUnauthorized();
});

it('accepts a request bearing a valid sanctum token', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$token}",
        'Accept' => 'application/json',
    ])->postJson('/mcp/intake', [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/list',
    ]);

    $response->assertOk();
    expect($response->json('result.tools'))->toBeArray()->not->toBeEmpty();
});

it('lists the intake authoring toolset without requiring pagination', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$token}",
        'Accept' => 'application/json',
    ])->postJson('/mcp/intake', [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/list',
    ]);

    $response->assertOk();

    $toolNames = collect($response->json('result.tools'))
        ->pluck('name')
        ->all();

    expect($toolNames)->toContain(
        'upsert-project',
        'upsert-capability',
        'upsert-source',
        'upsert-citation',
        'lookup-term',
    );
});

it('rejects a request with a bogus token', function () {
    $this->withHeaders([
        'Authorization' => 'Bearer not-a-real-token',
    ])->postJson('/mcp/intake', [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/list',
    ])->assertUnauthorized();
});
