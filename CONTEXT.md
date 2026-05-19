# Growth

Engineering-process tooling: an MCP server plus a webapp, over a shared
database. Growth exposes tools to client agents and records what they do; it
does not host or run an agent itself.

## Language

**MCP Server**:
Growth's tool/resource surface. It executes a request and returns a result —
there is no reasoning loop, personality, or instruction set running inside it.
_Avoid_: "the agent" (Growth is not one)

**Client Agent**:
The reasoning loop — Claude Code, claude.ai, or another MCP host — that decides
which tool to call and whether to ask the user first. Runs entirely client-side.
_Avoid_: "the agent" without a qualifier

**Persona**:
The instruction set a project Role carries — what that role is accountable for,
the judgement it brings, what it must not do, and which operations are routine
for it versus warrant user confirmation. Served to a Client Agent when it adopts
the Role. Advisory: the Client Agent chooses whether to honour it.
_Avoid_: "personality", "mode"; a Capability Surface has no Persona

**Capability Surface**:
A structural and semantic grouping of MCP tools, one per role-scoped MCP server.
A session connects to one. It is *not* a role. Named `CapabilitySurface` in
code; a session's binding is resolved by `SurfaceContext`.
_Avoid_: "operating role", "role" (for this concept)

**Role**:
A project-defined RACI accountability — `Engineering Lead`, `Product Lead`,
`Developer`. Named by the project, an open set. Held by Users and/or Agents,
carries a Persona, and is adopted by a session to colour how it operates.
_Avoid_: "operating role", "capability surface"

**Session**:
One Client Agent's connection to a Capability Surface. It may adopt at most one
Role; doing so binds that Role's Persona and attribution to everything the
session records thereafter. A session that adopts no Role still works — it just
operates without a Persona.
_Avoid_: "the agent" (a Session is a connection, not a reasoning loop)

**Agent**:
A named principal a Client Agent session may authenticate as, so recorded
events can be attributed to it. An attribution annotation, never a security
principal and never server-resident code.
_Avoid_: "autonomous agent", conflating with Client Agent

**Workspace**:
The tenant boundary. Every Project, and every recorded event, belongs to one.

## Relationships

- A **Workspace** contains **Projects**; a **Project** defines its own **Roles**.
- A **Role** is held by zero or more **Users** and/or **Agents** and carries one **Persona**.
- A **Capability Surface** exposes a subset of MCP tools; a **Session** connects to one.
- A **Session** adopts at most one **Role**; the **Role**, not the **Surface**, carries the **Persona**.
- Growth **serves** Personas, tools, and tool annotations, and **records**
  attribution. The **Client Agent** decides and acts. Growth never supervises it.

## Example dialogue

> **Dev:** "When a session operates as the Planning role, can Growth stop it
> from deleting a project?"
> **Domain expert:** "No. 'Planning' is a Capability Surface, not a Role, and
> either way Growth is an MCP server — it can't gate the Client Agent. It can
> only annotate `delete-project` as destructive, put 'confirm before deleting'
> in the served Persona, and require a hard `confirm_name` argument. Whether a
> prompt appears is the Client Agent's call."

## Flagged ambiguities

- **"Role"** named two concepts: the project RACI **Role** and a code enum
  that is really a **Capability Surface** (a tool-grouping with a 1:1 MCP
  server), not a role. Resolved (#318): the enum and its satellites were
  renamed — `CapabilitySurface`, `SurfaceContext`, `acting_surface` — and
  **Role** is reserved for the project entity.
- **"Agent"** named two concepts: **Agent**-as-principal (an attribution
  annotation — real, server-side) and agent-as-autonomous-actor (the **Client
  Agent** — client-side). Resolved: Growth hosts neither an agent nor its
  reasoning loop; it attributes events to an **Agent** principal and serves
  context to a **Client Agent**.
