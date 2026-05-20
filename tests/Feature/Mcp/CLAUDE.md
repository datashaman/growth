# MCP feature tests

Pest feature tests for everything under `app/Mcp/**`. Conventions:

## Actor setup

```php
beforeEach(function () {
    Passport::actingAs(User::factory()->create(), ['mcp:use']);
});
```

The `mcp:use` scope is what `auth:api` on `/mcp/*` checks. Tests against the
HTTP transport fail with `assertUnauthorized()` without it.

## HTTP transport

JSON-RPC POSTs against the surface server:

```php
$this->postJson('/mcp/intake', [
    'jsonrpc' => '2.0',
    'id' => 1,
    'method' => 'tools/list',
])->assertOk();
```

See `HttpAuthTest.php` for auth flows; the 401 path asserts
`WWW-Authenticate: Bearer resource_metadata=…` is set.

## Resource / app rendering

Use the `readResource(ServerClass, 'uri://…')` helper:

```php
readResource(ReadonlyServer::class, 'ui://resources/gate-status')
    ->assertOk()
    ->assertSee('createMcpApp');
```

`assertSee(string, false)` (second arg false) for substrings that contain
HTML or JS punctuation — see `GateStatusAppTest.php`.

## Sampling tools

Don't go through the HTTP route — invoke `handle()` directly with a
`FakeTransporter`-backed `Sampling`:

```php
$transport = new FakeTransporter;
$transport->expectResponse(['role' => 'assistant', 'content' => ['type' => 'text', 'text' => '…']]);

$sampling = new Sampling($transport, ['sampling' => []]);
app(TriageFeedback::class)->handle(new Request([...]), $sampling);

$requests = $transport->sentRequests(); // assert on sampling/createMessage
```

See `TriageFeedbackSamplingTest.php` for the full pattern, including the
no-capability and reason-supplied branches.

## Don't

- Don't mock the DB. Use factories + `RefreshDatabase` (it's the default).
- Don't run the whole suite to verify one change — pass the file path or
  `--filter`.
