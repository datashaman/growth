<?php

use App\Models\User;
use Laravel\Passport\Passport;

it('rejects unauthenticated POSTs to the MCP HTTP route', function () {
    $this->postJson('/mcp/intake', [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/list',
    ])->assertUnauthorized();
});

it('accepts a request authenticated by Passport OAuth', function () {
    $user = User::factory()->create();
    Passport::actingAs($user, ['mcp:use']);

    $response = $this->postJson('/mcp/intake', [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/list',
    ]);

    $response->assertOk();
    expect($response->json('result.tools'))->toBeArray()->not->toBeEmpty();
});

it('lists the intake authoring toolset without requiring pagination', function () {
    $user = User::factory()->create();
    Passport::actingAs($user, ['mcp:use']);

    $response = $this->postJson('/mcp/intake', [
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
        'upsert-requirements',
        'upsert-source',
        'upsert-citation',
        'lookup-term',
    );
});

it('advertises OAuth resource metadata for unauthenticated MCP requests', function () {
    $response = $this->postJson('/mcp/intake', [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/list',
    ])->assertUnauthorized();

    expect($response->headers->get('WWW-Authenticate'))->toContain('resource_metadata=');
});

it('exposes MCP OAuth discovery metadata', function () {
    $this->getJson('/.well-known/oauth-protected-resource/mcp/intake')
        ->assertOk()
        ->assertJsonPath('scopes_supported.0', 'mcp:use');

    $this->getJson('/.well-known/oauth-authorization-server/mcp/intake')
        ->assertOk()
        ->assertJsonPath('scopes_supported.0', 'mcp:use')
        ->assertJsonPath('grant_types_supported.0', 'authorization_code');
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
