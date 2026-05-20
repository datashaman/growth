# MCP feature tests

Pest feature tests for everything under `app/Mcp/**`. Conventions:

## Actor setup

```php
beforeEach(function () {
    Passport::actingAs(User::factory()->create(), ['mcp:use']);
});
```

`auth:api` on `/mcp/*` (see `routes/ai.php`) only authenticates the token;
it doesn't enforce scopes by itself. `mcp:use` is what MCP clients are
expected to hold (the OAuth discovery routes advertise it; `Common/Doctor`
calls `$token->can('mcp:use')` to surface a clear error). Tests grant it
in `beforeEach` to match what a real client carries.

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
