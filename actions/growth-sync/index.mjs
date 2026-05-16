// Growth sync runner. Translates GitHub pull_request events into MCP
// calls against a Growth instance. Pure helpers and the orchestration
// `run` function are exported for testing; the bottom of the file is the
// GitHub Action entrypoint.

const TRAILER_KEY = 'Growth-Work-Item';

/**
 * Extract the work item id from a commit message's `Growth-Work-Item:`
 * trailer. The last matching trailer wins, per git trailer convention.
 * Returns null when no trailer is present.
 */
export function parseTrailer(commitMessage) {
  const pattern = new RegExp(`^${TRAILER_KEY}:\\s*(\\S+)\\s*$`, 'gim');
  let workItemId = null;
  let match;
  while ((match = pattern.exec(commitMessage ?? '')) !== null) {
    workItemId = match[1];
  }
  return workItemId;
}

/**
 * Decide which commit carries the trailer for a pull_request event:
 * the merge commit once the PR is merged, otherwise the head commit.
 * Returns null for a PR closed without merging — there is nothing to record.
 */
export function resolveCommitSha(event) {
  const pr = event.pull_request ?? {};
  if (event.action === 'closed') {
    return pr.merged ? (pr.merge_commit_sha ?? null) : null;
  }
  return pr.head?.sha ?? null;
}

/**
 * Build the upsert-delivery-link arguments for a pull request. The ref is
 * `#<num>` so synchronize and merge events resolve to the same
 * (work_item_id, type, ref) row and the upsert stays idempotent.
 */
export function buildDeliveryLinkArgs(event, workItemId) {
  const pr = event.pull_request ?? {};
  return {
    work_item_id: workItemId,
    type: 'pull_request',
    ref: `#${pr.number}`,
    url: pr.html_url,
    description: `GitHub pull request: ${pr.title ?? ''}`.trim(),
  };
}

/**
 * Orchestrate one pull_request event. Dependencies are injected so this
 * is testable without network access. Never throws for a missing trailer
 * or an unresolvable work item — it logs a warning and skips.
 */
export async function run({ event, getCommitMessage, callTool, log }) {
  const sha = resolveCommitSha(event);
  if (sha === null) {
    log.warn('Pull request closed without merging; nothing to record.');
    return { skipped: true };
  }

  const commitMessage = await getCommitMessage(sha);
  const workItemId = parseTrailer(commitMessage);
  if (workItemId === null) {
    log.warn(`No ${TRAILER_KEY} trailer on commit ${sha}; skipping.`);
    return { skipped: true };
  }

  const args = buildDeliveryLinkArgs(event, workItemId);
  const result = await callTool('upsert-delivery-link', args);
  if (result.isError) {
    log.warn(`Growth rejected the delivery link: ${result.errorText}; skipping.`);
    return { skipped: true };
  }

  log.info(`Recorded delivery link ${args.ref} on work item ${workItemId}.`);
  return { skipped: false, structured: result.structured };
}

/**
 * Fetch a commit message from the GitHub REST API. One request, no clone.
 */
function makeGitHubClient({ apiUrl, repository, token, fetchFn }) {
  return async function getCommitMessage(sha) {
    const response = await fetchFn(`${apiUrl}/repos/${repository}/commits/${sha}`, {
      headers: {
        Authorization: `Bearer ${token}`,
        Accept: 'application/vnd.github+json',
        'User-Agent': 'growth-sync',
      },
    });
    if (!response.ok) {
      throw new Error(`GitHub API ${response.status} fetching commit ${sha}`);
    }
    const body = await response.json();
    return body.commit?.message ?? '';
  };
}

/**
 * Call a Growth MCP tool over the stateless HTTP transport. Protocol-level
 * JSON-RPC errors throw; tool-level errors are returned as { isError }.
 */
function makeMcpClient({ growthUrl, token, fetchFn }) {
  return async function callTool(name, args) {
    const response = await fetchFn(`${growthUrl.replace(/\/$/, '')}/mcp/all`, {
      method: 'POST',
      headers: {
        Authorization: `Bearer ${token}`,
        'Content-Type': 'application/json',
        Accept: 'application/json',
      },
      body: JSON.stringify({
        jsonrpc: '2.0',
        id: 1,
        method: 'tools/call',
        params: { name, arguments: args },
      }),
    });
    if (!response.ok) {
      throw new Error(`Growth MCP HTTP ${response.status} calling ${name}`);
    }
    const body = await response.json();
    if (body.error) {
      throw new Error(`Growth MCP error calling ${name}: ${body.error.message}`);
    }
    const result = body.result ?? {};
    const errorText = (result.content ?? [])
      .filter((item) => item.type === 'text')
      .map((item) => item.text)
      .join(' ');
    return {
      isError: result.isError === true,
      errorText,
      structured: result.structuredContent ?? null,
    };
  };
}

async function main() {
  const { GITHUB_EVENT_NAME, GITHUB_EVENT_PATH, GITHUB_REPOSITORY } = process.env;
  if (GITHUB_EVENT_NAME !== 'pull_request') {
    console.log(`Event ${GITHUB_EVENT_NAME} is not handled; skipping.`);
    return;
  }

  const growthUrl = process.env.GROWTH_URL;
  const growthToken = process.env.GROWTH_TOKEN;
  const githubToken = process.env.GITHUB_TOKEN;
  if (!growthUrl || !growthToken) {
    throw new Error('GROWTH_URL and GROWTH_TOKEN must be set.');
  }

  const { readFile } = await import('node:fs/promises');
  const event = JSON.parse(await readFile(GITHUB_EVENT_PATH, 'utf8'));

  const getCommitMessage = makeGitHubClient({
    apiUrl: process.env.GITHUB_API_URL ?? 'https://api.github.com',
    repository: GITHUB_REPOSITORY,
    token: githubToken,
    fetchFn: fetch,
  });
  const callTool = makeMcpClient({ growthUrl, token: growthToken, fetchFn: fetch });

  await run({
    event,
    getCommitMessage,
    callTool,
    log: { info: (m) => console.log(m), warn: (m) => console.warn(m) },
  });
}

if (process.argv[1] && import.meta.url === `file://${process.argv[1]}`) {
  main().catch((error) => {
    console.error(error.message);
    process.exit(1);
  });
}
