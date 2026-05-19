# Growth is an MCP server, not an agent host

Growth exposes MCP tools, resources, and per-context persona instructions, and
records attribution for every invocation. The reasoning loop that decides which
tool to call — and whether to confirm with the user first — runs entirely in
the client (Claude Code, claude.ai, another MCP host), never in Growth. We
decided to treat this as a hard architectural boundary rather than build
toward server-side agent control, because the server genuinely cannot observe
or alter client behaviour at tool-call time.

## Consequences

- Growth **cannot gate confirmations**. It influences a client agent only by
  what it *serves* — honest tool annotations (`destructiveHint` etc.) and
  persona instructions — both advisory. A client may ignore them.
- Growth's only hard guards are **server-side**: validation, workspace/role
  scoping, and explicit confirmation arguments (e.g. `DeleteProject.confirm_name`).
  These stay; they are not "confirmation busywork" to be removed.
- Features framed as "agent autonomy", "agent lifecycle", or a server-enforced
  "human-in-the-loop split" assume a server-resident agent and are out of scope
  by construction. Per-agent *attribution* and *reporting* (recording what an
  agent principal did) remain valid — they observe, they do not supervise.
- A `Role` is a project-defined accountability a user or agent role-plays; the
  code's `OperatingRole` enum is a misnamed *capability surface*. See `CONTEXT.md`.
