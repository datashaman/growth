# Artifact generation uses bundled brief resources

When a Client Agent is expected to generate a durable artifact — a requirement,
plan, work item, mockup, verification case, change request, review, or
architecture view — Growth should point it at a use-case-specific **brief**
resource rather than making the tool or prompt list a long sequence of discovery
calls.

The artifact-generation surface still owns the write. The brief owns the
context: the owner artifact, nearby trace, requirements, architecture views and
elements, risks, reviews, existing sibling artifacts, evidence, and any
artifact-specific guidance. For example, a mockup write should point to a
mockup design brief that bundles the work item or requirement, linked
requirements, existing mockups, and relevant architecture context.

We chose this because the Client Agent makes better artifacts when Growth
pre-composes the context around the task it is about to perform. A checklist of
tools such as "call list requirements, then list architecture views, then
trace-query..." is brittle: clients may skip steps, call them in the wrong
order, or lose the relationship between the results. A brief resource gives the
Client Agent one stable context object to read before generating the artifact.

This does not make Growth an agent host or supervisor. Per ADR-0001, Growth can
serve context and record what happened, but it cannot prove the Client Agent
read the brief or force it to follow the guidance. Server-side validation still
protects invariants; brief resources improve artifact quality by making the
right context easy to consume.

## Consequences

- Artifact-generating tools and prompts should prefer "read this brief first"
  guidance over enumerating many low-level discovery calls.
- New artifact-generation features should ask which brief resource the Client
  Agent should read before adding more tool metadata.
- Brief resources are read-only, task-shaped context bundles. They may aggregate
  existing resources, trace edges, model relationships, and advisory findings,
  but they are not a new workflow state or enforcement layer.
- Brief URIs should name the artifact-generation use case, not the underlying
  implementation. Examples: `mockup-design-brief`, `implementation-brief`,
  `verification-brief`, `review-brief`, `change-impact-brief`.
- Existing low-level list and trace tools remain useful for exploration and
  follow-up. The brief is the starting context, not the only permitted context.
- Briefs should stay compact enough to fit normal MCP context budgets. If a
  project is large, the brief should include summaries and links to deeper
  resources instead of dumping every row.
