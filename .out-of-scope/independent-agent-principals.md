# Independent Agent Principals

Growth does not support independent agents authenticating as their own security
principal.

## Why this is out of scope

Growth is an MCP server and webapp for serving project context, exposing tools,
and recording what a Client Agent does. The reasoning loop and operational
control stay in the MCP client. In the current model, a human-authenticated
session may carry an `Agent` attribution annotation, but that annotation is not
the security principal.

Making `Agent` directly authenticatable would turn agent identity into an
authorization and credential-management boundary. That would reopen the settled
architecture around users, sessions, workspace scoping, role adoption, token
ownership, revocation, audit semantics, and unattended operation. Those concerns
belong outside Growth's current scope.

Growth can continue to improve attribution, reporting, and advisory Persona
context for Client Agents. It should not become the system that issues and
governs standalone credentials for unattended agents.

## Prior requests

- #319 - "Make the Agent an authenticatable principal (independent agents)"
