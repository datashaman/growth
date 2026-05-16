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
 * The first pull request a check_run belongs to, or null when the check
 * run is not associated with a PR (e.g. a check on a bare branch push).
 */
export function resolveCheckRunPullRequest(event) {
  const pullRequests = event.check_run?.pull_requests ?? [];
  return pullRequests.length > 0 ? pullRequests[0] : null;
}

/**
 * Build the upsert-check-run arguments from a check_run event. The
 * (delivery link, provider, name) triple keeps re-runs idempotent.
 * Undefined optional fields are dropped by JSON.stringify.
 */
export function buildCheckRunArgs(event, deliveryLinkId) {
  const checkRun = event.check_run ?? {};
  return {
    work_item_delivery_link_id: deliveryLinkId,
    provider: 'github-actions',
    name: checkRun.name,
    run_ref: checkRun.id != null ? String(checkRun.id) : undefined,
    status: checkRun.status,
    conclusion: checkRun.conclusion ?? undefined,
    url: checkRun.html_url ?? undefined,
    started_at: checkRun.started_at ?? undefined,
    completed_at: checkRun.completed_at ?? undefined,
  };
}

/**
 * Orchestrate one pull_request event. Never throws for a missing trailer
 * or an unresolvable work item — it logs a warning and skips.
 */
async function runPullRequest({ event, getCommitMessage, callTool, log }) {
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
 * Orchestrate one check_run event: resolve the PR's delivery link (creating
 * it if the PR event has not been seen yet) then record check evidence.
 */
async function runCheckRun({ event, repository, getCommitMessage, callTool, log }) {
  const pullRequest = resolveCheckRunPullRequest(event);
  if (pullRequest === null) {
    log.warn('Check run has no associated pull request; skipping.');
    return { skipped: true };
  }

  const sha = event.check_run?.head_sha;
  const commitMessage = await getCommitMessage(sha);
  const workItemId = parseTrailer(commitMessage);
  if (workItemId === null) {
    log.warn(`No ${TRAILER_KEY} trailer on commit ${sha}; skipping.`);
    return { skipped: true };
  }

  const linkArgs = {
    work_item_id: workItemId,
    type: 'pull_request',
    ref: `#${pullRequest.number}`,
    url: `https://github.com/${repository}/pull/${pullRequest.number}`,
  };
  const linkResult = await callTool('upsert-delivery-link', linkArgs);
  if (linkResult.isError) {
    log.warn(`Growth rejected the delivery link: ${linkResult.errorText}; skipping.`);
    return { skipped: true };
  }

  const checkArgs = buildCheckRunArgs(event, linkResult.structured?.id);
  const checkResult = await callTool('upsert-check-run', checkArgs);
  if (checkResult.isError) {
    log.warn(`Growth rejected the check run: ${checkResult.errorText}; skipping.`);
    return { skipped: true };
  }

  log.info(`Recorded check run "${checkArgs.name}" (${checkArgs.conclusion ?? checkArgs.status}) on ${linkArgs.ref}.`);
  return { skipped: false, structured: checkResult.structured };
}

/**
 * Dispatch a GitHub event to its handler. Dependencies are injected so the
 * handlers are testable without network access.
 */
export async function run(context) {
  if (context.eventName === 'pull_request') {
    return runPullRequest(context);
  }
  if (context.eventName === 'check_run') {
    return runCheckRun(context);
  }

  context.log.info(`Event ${context.eventName} is not handled; skipping.`);
  return { skipped: true };
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
    eventName: GITHUB_EVENT_NAME,
    event,
    repository: GITHUB_REPOSITORY,
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
