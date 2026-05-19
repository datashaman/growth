# Growth keeps server-side MCP session state

The MCP HTTP transport is stateless: `laravel/mcp` issues a session id at
`initialize` but persists nothing keyed on it, and each JSON-RPC request runs
in its own PHP process. To let a Client Agent adopt a project Role *mid-session*
(the `adopt-role` tool) and have that binding hold across subsequent tool calls,
Growth introduces its own `mcp_sessions` store keyed by the MCP session id and
the authenticated user. We chose this over passing the role on every request
because a per-call argument is just a worse version of a connect-time token
binding — it cannot express "I am this role now" as a durable session fact, and
it leaves attribution at the mercy of a parameter the client may omit.

## Consequences

- Growth holds state the transport does not. A future reader seeing a session
  table behind a "stateless" protocol should read this ADR: it is deliberate.
- The store is keyed by the client-supplied session id **and** the token's
  user id, so spoofing another session id only ever reaches the spoofer's own
  sessions.
- Rows are written lazily on first `adopt-role` (unbound sessions never touch
  the table) and pruned on age, like `tool_invocations`.
- The binding works identically on stdio and HTTP — the store, not in-process
  memory, is the single mechanism.
- This does not soften ADR-0001: the session's Role is served as advisory
  Persona and recorded for attribution. It never gates a tool call.
