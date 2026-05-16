// Growth sync runner. Translates GitHub pull_request events into MCP
// calls against a Growth instance. Pure helpers and the orchestration
// `run` function are exported for testing; the bottom of the file is the
// GitHub Action entrypoint.

const TRAILER_KEY = 'Growth-Work-Item';

// Name of the GitHub check growth-sync posts back to a pull request to
// make a missed work item attribution loud instead of silent.
const ATTRIBUTION_CHECK_NAME = 'Growth: work item attribution';

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
 * The branch a GitHub event belongs to, or null. pull_request carries it
 * directly; check_run and workflow_run take it from the head pull request.
 * The branch is what binds trailer-less commits to a work item.
 */
export function resolveEventBranch(eventName, event, pullRequest = null) {
  if (eventName === 'pull_request') {
    return event.pull_request?.head?.ref ?? null;
  }
  if (eventName === 'check_run') {
    return pullRequest?.head?.ref ?? event.check_run?.check_suite?.head_branch ?? null;
  }
  if (eventName === 'workflow_run') {
    return event.workflow_run?.head_branch ?? null;
  }
  return null;
}

/**
 * Whether a pull request originates from a fork — its head repository
 * differs from the base. Branch bindings only make sense within the base
 * repository: two forks can each open a pull request with the same head
 * ref, so a fork branch must never create or resolve a base-repo branch
 * binding. A missing head repo (older payloads, a deleted fork) is treated
 * as same-repo, matching the attribution-check guard.
 */
export function isForkPullRequest(pullRequest, repository) {
  const headRepo = pullRequest?.head?.repo?.full_name;
  return Boolean(headRepo && headRepo !== repository);
}

/**
 * Resolve a work item for a trailer-less commit by looking up a branch
 * delivery link. Returns the work item id, or null when nothing is bound.
 */
async function resolveWorkItemByBranch({ callTool, repository, branch, log }) {
  if (!branch) {
    return { workItemId: null, ambiguous: false };
  }
  const result = await callTool('resolve-work-item-by-branch', {
    github_repo: repository,
    branch,
  });
  if (result.isError) {
    throw new Error(`Growth rejected the branch lookup: ${result.errorText}`);
  }
  const ambiguous = result.structured?.ambiguous === true;
  if (ambiguous) {
    log?.warn(`Branch ${branch} is bound to more than one work item; cannot attribute trailer-less commits.`);
  }
  return {
    workItemId: result.structured?.found ? result.structured.work_item_id : null,
    ambiguous,
  };
}

/**
 * Record a GitHub event that could not be attributed so the gap surfaces
 * on the Evidence page. Best-effort: a rejected record is logged, not
 * thrown — the triage net must not fail the whole sync.
 */
async function recordUnattributedEvent({ callTool, repository, eventName, branch, sha, reason, url, log }) {
  if (!sha) {
    return;
  }
  try {
    const result = await callTool('record-unattributed-event', {
      github_repo: repository,
      event_type: eventName,
      branch: branch ?? undefined,
      commit_sha: sha,
      reason,
      url: url ?? undefined,
    });
    if (result.isError) {
      log.warn(`Growth rejected the unattributed-event record: ${result.errorText}`);
    }
  } catch (error) {
    log.warn(`Could not record the unattributed event: ${error.message}`);
  }
}

/**
 * Record a branch delivery link so later events on the same branch whose
 * commits lack a trailer still attribute to this work item. Idempotent on
 * (work_item_id, type, ref).
 */
async function recordBranchBinding({ callTool, repository, workItemId, branch }) {
  // Branch names may contain URL-reserved characters (e.g. `issue#123`);
  // encode each path segment so the tree URL points at the real branch.
  const branchPath = branch.split('/').map(encodeURIComponent).join('/');
  const result = await callTool('upsert-delivery-link', {
    work_item_id: workItemId,
    type: 'branch',
    ref: branch,
    url: `https://github.com/${repository}/tree/${branchPath}`,
  });
  if (result.isError) {
    throw new Error(`Growth rejected the branch link: ${result.errorText}`);
  }
}

/**
 * Resolve a work item from a commit message, falling back to the branch
 * binding when the commit carries no trailer. When the trailer resolves
 * and a branch is known, the branch is bound so later trailer-less events
 * still attribute. Returns the work item id, or null when unattributable.
 */
async function attributeWorkItem({ callTool, repository, eventName, commitMessage, branch, sha, url, isFork = false, log }) {
  const trailerId = parseTrailer(commitMessage);
  if (trailerId !== null) {
    // Two forks can share a head ref, so a fork branch must never become a
    // base-repo branch binding. The PR-number link still carries attribution
    // for later trailer-less events on the same pull request.
    if (branch && !isFork) {
      await recordBranchBinding({ callTool, repository, workItemId: trailerId, branch });
    }
    return trailerId;
  }

  // A fork's trailer-less commit cannot borrow a base-repo branch binding:
  // another fork may have created it for an unrelated same-named branch.
  const { workItemId, ambiguous } = isFork
    ? { workItemId: null, ambiguous: false }
    : await resolveWorkItemByBranch({ callTool, repository, branch, log });
  if (workItemId !== null) {
    log.info(`Attributed commit ${sha} to work item ${workItemId} via branch ${branch}.`);
    return workItemId;
  }

  log.warn(`No ${TRAILER_KEY} trailer on commit ${sha} and no work item bound to branch ${branch ?? '(unknown)'}; skipping.`);
  await recordUnattributedEvent({
    callTool,
    repository,
    eventName,
    branch,
    sha,
    reason: ambiguous ? 'ambiguous_branch' : 'missing_link',
    url,
    log,
  });
  return null;
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
 * Map a GitHub deployment_status state to a Growth deployment status.
 * Returns null for states Growth does not record (pending, in_progress,
 * queued, inactive) so the caller can skip them.
 */
export function mapDeploymentState(githubState) {
  switch (githubState) {
    case 'success':
      return 'succeeded';
    case 'failure':
    case 'error':
      return 'failed';
    default:
      return null;
  }
}

/**
 * Build the GitHub check-run payload for the work item attribution check.
 * A resolved work item passes; an unresolved one fails (blocking) or is
 * flagged neutral (advisory), with output naming the two ways to fix it.
 */
export function buildAttributionCheckArgs({ headSha, workItemId, branch, advisory }) {
  const resolved = workItemId !== null && workItemId !== undefined;
  const output = resolved
    ? {
        title: `Attributed to work item ${workItemId}`,
        summary: `This pull request's commits are attributed to Growth work item ${workItemId}.`,
      }
    : {
        title: 'No Growth work item resolved',
        summary: [
          'growth-sync could not attribute this pull request to a Growth work item, ',
          'so its delivery evidence will not be recorded.',
          '\n\nFix it one of two ways:\n',
          `- Add a \`${TRAILER_KEY}: <id>\` trailer to a commit on \`${branch ?? 'this branch'}\`, or\n`,
          '- Bind the branch to a work item with the `upsert-delivery-link` MCP tool (type `branch`).',
          '\n\nRe-run this workflow once the link exists and the check will pass.',
        ].join(''),
      };

  return {
    name: ATTRIBUTION_CHECK_NAME,
    head_sha: headSha,
    status: 'completed',
    conclusion: resolved ? 'success' : (advisory ? 'neutral' : 'failure'),
    output,
  };
}

/**
 * Post the work item attribution check back to the pull request. Skipped
 * for closed PRs (the merge already happened) and for fork PRs (their
 * GITHUB_TOKEN is read-only, so the Checks API POST would 403). A failed
 * post is logged, not thrown, so it never fails the whole sync.
 */
async function reportAttribution({ event, repository, workItemId, branch, postCheckRun, advisory, log }) {
  if (!postCheckRun || event.action === 'closed') {
    return;
  }

  const pr = event.pull_request ?? {};
  const headSha = pr.head?.sha;
  if (!headSha) {
    return;
  }
  if (pr.head?.repo?.full_name && pr.head.repo.full_name !== repository) {
    log.info('Pull request is from a fork; skipping the attribution check.');
    return;
  }

  const args = buildAttributionCheckArgs({ headSha, workItemId, branch, advisory });
  try {
    await postCheckRun(args);
    log.info(`Posted "${ATTRIBUTION_CHECK_NAME}" check (${args.conclusion}) on ${headSha}.`);
  } catch (error) {
    log.warn(`Could not post the attribution check: ${error.message}`);
  }
}

/**
 * Orchestrate one pull_request event. A missing trailer or an unmerged
 * close is logged and skipped; a rejected tool call throws so the
 * workflow fails rather than silently passing.
 */
async function runPullRequest({ event, repository, getCommitMessage, callTool, postCheckRun, attributionCheckAdvisory, log }) {
  const sha = resolveCommitSha(event);
  if (sha === null) {
    log.warn('Pull request closed without merging; nothing to record.');
    return { skipped: true };
  }

  const branch = resolveEventBranch('pull_request', event);
  const commitMessage = await getCommitMessage(sha);
  const workItemId = await attributeWorkItem({
    callTool,
    repository,
    eventName: 'pull_request',
    commitMessage,
    branch,
    sha,
    url: event.pull_request?.html_url,
    isFork: isForkPullRequest(event.pull_request, repository),
    log,
  });

  await reportAttribution({
    event,
    repository,
    workItemId,
    branch,
    postCheckRun,
    advisory: attributionCheckAdvisory,
    log,
  });

  if (workItemId === null) {
    return { skipped: true };
  }

  const args = buildDeliveryLinkArgs(event, workItemId);
  const result = await callTool('upsert-delivery-link', args);
  if (result.isError) {
    throw new Error(`Growth rejected the delivery link: ${result.errorText}`);
  }

  log.info(`Recorded delivery link ${args.ref} on work item ${workItemId}.`);
  return { skipped: false, structured: result.structured };
}

/**
 * Orchestrate one check_run event: resolve the PR's delivery link (creating
 * it if the PR event has not been seen yet) then record check evidence.
 */
async function runCheckRun({ event, repository, getCommitMessage, callTool, log }) {
  // growth-sync posts its own attribution check; ingesting it would record
  // the check as evidence of itself, so skip it.
  if (event.check_run?.name === ATTRIBUTION_CHECK_NAME) {
    log.info('Check run is growth-sync\'s own attribution check; skipping.');
    return { skipped: true };
  }

  const pullRequest = resolveCheckRunPullRequest(event);
  if (pullRequest === null) {
    log.warn('Check run has no associated pull request; skipping.');
    return { skipped: true };
  }

  const branch = resolveEventBranch('check_run', event, pullRequest);
  const sha = event.check_run?.head_sha;
  const commitMessage = await getCommitMessage(sha);
  const workItemId = await attributeWorkItem({
    callTool,
    repository,
    eventName: 'check_run',
    commitMessage,
    branch,
    sha,
    url: event.check_run?.html_url,
    log,
  });
  if (workItemId === null) {
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
    throw new Error(`Growth rejected the delivery link: ${linkResult.errorText}`);
  }

  const checkArgs = buildCheckRunArgs(event, linkResult.structured?.id);
  const checkResult = await callTool('upsert-check-run', checkArgs);
  if (checkResult.isError) {
    throw new Error(`Growth rejected the check run: ${checkResult.errorText}`);
  }

  log.info(`Recorded check run "${checkArgs.name}" (${checkArgs.conclusion ?? checkArgs.status}) on ${linkArgs.ref}.`);
  return { skipped: false, structured: checkResult.structured };
}

/**
 * The first pull request a workflow_run belongs to, or null. GitHub leaves
 * this empty for runs triggered by fork pull requests.
 */
export function resolveWorkflowRunPullRequest(event) {
  const pullRequests = event.workflow_run?.pull_requests ?? [];
  return pullRequests.length > 0 ? pullRequests[0] : null;
}

/**
 * Build the upsert-check-run arguments from a workflow_run event. A whole
 * GitHub Actions run maps to one check record — coarser than check_run,
 * but check_run events never fire for Actions-authored checks.
 */
export function buildWorkflowRunArgs(event, deliveryLinkId) {
  const run = event.workflow_run ?? {};
  return {
    work_item_delivery_link_id: deliveryLinkId,
    provider: 'github-actions',
    name: run.name,
    run_ref: run.id != null ? String(run.id) : undefined,
    status: run.status,
    conclusion: run.conclusion ?? undefined,
    url: run.html_url ?? undefined,
    started_at: run.run_started_at ?? undefined,
    completed_at: run.updated_at ?? undefined,
  };
}

/**
 * Orchestrate one workflow_run event: resolve the PR's delivery link
 * (creating it if the PR event has not been seen yet) then record the
 * run as check evidence. This is the GitHub Actions counterpart to
 * runCheckRun, which only fires for third-party CI providers.
 */
async function runWorkflowRun({ event, repository, getCommitMessage, callTool, log }) {
  const pullRequest = resolveWorkflowRunPullRequest(event);
  if (pullRequest === null) {
    log.warn('Workflow run has no associated pull request; skipping.');
    return { skipped: true };
  }

  const run = event.workflow_run ?? {};
  const branch = resolveEventBranch('workflow_run', event);
  const commitMessage = await getCommitMessage(run.head_sha, run.head_repository?.full_name);
  const workItemId = await attributeWorkItem({
    callTool,
    repository,
    eventName: 'workflow_run',
    commitMessage,
    branch,
    sha: run.head_sha,
    url: run.html_url,
    log,
  });
  if (workItemId === null) {
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
    throw new Error(`Growth rejected the delivery link: ${linkResult.errorText}`);
  }

  const checkArgs = buildWorkflowRunArgs(event, linkResult.structured?.id);
  const checkResult = await callTool('upsert-check-run', checkArgs);
  if (checkResult.isError) {
    throw new Error(`Growth rejected the check run: ${checkResult.errorText}`);
  }

  log.info(`Recorded workflow run "${checkArgs.name}" (${checkArgs.conclusion ?? checkArgs.status}) on ${linkArgs.ref}.`);
  return { skipped: false, structured: checkResult.structured };
}

/**
 * Build the upsert-deployment arguments from a deployment_status event.
 * provider + external_ref make the upsert idempotent across the multiple
 * deployment_status events GitHub fires for one deployment.
 */
export function buildDeploymentArgs(event, projectId, status) {
  const deployment = event.deployment ?? {};
  const deploymentStatus = event.deployment_status ?? {};
  return {
    project_id: projectId,
    environment: deploymentStatus.environment ?? deployment.environment,
    status,
    provider: 'github',
    external_ref: String(deployment.id),
    url: deploymentStatus.environment_url || deploymentStatus.target_url || undefined,
    deployed_at: deploymentStatus.created_at ?? undefined,
  };
}

/**
 * Orchestrate one deployment_status event: resolve the repository's Growth
 * project, then upsert the deployment. Unmapped states and unbound repos
 * are logged and skipped.
 */
async function runDeployment({ event, repository, callTool, log }) {
  const status = mapDeploymentState(event.deployment_status?.state);
  if (status === null) {
    log.info(`Deployment state ${event.deployment_status?.state} is not recorded; skipping.`);
    return { skipped: true };
  }

  const resolved = await callTool('resolve-project-by-repo', { github_repo: repository });
  if (resolved.isError) {
    throw new Error(`Growth rejected the repo lookup: ${resolved.errorText}`);
  }
  const projectId = resolved.structured?.project_id;
  if (!resolved.structured?.found || !projectId) {
    log.warn(`No Growth project bound to ${repository}; skipping.`);
    return { skipped: true };
  }

  const args = buildDeploymentArgs(event, projectId, status);
  const result = await callTool('upsert-deployment', args);
  if (result.isError) {
    throw new Error(`Growth rejected the deployment: ${result.errorText}`);
  }

  log.info(`Recorded ${status} deployment to ${args.environment} on project ${projectId}.`);
  return { skipped: false, structured: result.structured };
}

/**
 * Build the upsert-release arguments from a release event. The GitHub tag
 * is the version, so the upsert resolves to the same project/version row
 * if the release is published more than once.
 */
export function buildReleaseArgs(event, projectId) {
  const release = event.release ?? {};
  return {
    project_id: projectId,
    version: release.tag_name,
    name: release.name || undefined,
    status: 'released',
    released_at: release.published_at ?? undefined,
    notes: release.body || undefined,
  };
}

/**
 * Orchestrate one release event: resolve the repository's Growth project,
 * then upsert the release. Unbound repos are logged and skipped.
 */
async function runRelease({ event, repository, callTool, log }) {
  const resolved = await callTool('resolve-project-by-repo', { github_repo: repository });
  if (resolved.isError) {
    throw new Error(`Growth rejected the repo lookup: ${resolved.errorText}`);
  }
  const projectId = resolved.structured?.project_id;
  if (!resolved.structured?.found || !projectId) {
    log.warn(`No Growth project bound to ${repository}; skipping.`);
    return { skipped: true };
  }

  const args = buildReleaseArgs(event, projectId);
  const result = await callTool('upsert-release', args);
  if (result.isError) {
    throw new Error(`Growth rejected the release: ${result.errorText}`);
  }

  log.info(`Recorded release ${args.version} on project ${projectId}.`);
  return { skipped: false, structured: result.structured };
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
  if (context.eventName === 'workflow_run') {
    return runWorkflowRun(context);
  }
  if (context.eventName === 'deployment_status') {
    return runDeployment(context);
  }
  if (context.eventName === 'release') {
    return runRelease(context);
  }

  context.log.info(`Event ${context.eventName} is not handled; skipping.`);
  return { skipped: true };
}

/**
 * Fetch a commit message from the GitHub REST API. One request, no clone.
 * `repositoryOverride` targets a fork when the commit lives outside the
 * base repository — workflow_run events carry head_repository for this.
 */
function makeGitHubClient({ apiUrl, repository, token, fetchFn }) {
  return async function getCommitMessage(sha, repositoryOverride) {
    const repo = repositoryOverride ?? repository;
    const response = await fetchFn(`${apiUrl}/repos/${repo}/commits/${sha}`, {
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
 * Post a check run to a repository via the GitHub Checks API. Used to
 * surface the work item attribution result back onto the pull request.
 */
function makeCheckRunPoster({ apiUrl, repository, token, fetchFn }) {
  return async function postCheckRun(args) {
    const response = await fetchFn(`${apiUrl}/repos/${repository}/check-runs`, {
      method: 'POST',
      headers: {
        Authorization: `Bearer ${token}`,
        Accept: 'application/vnd.github+json',
        'Content-Type': 'application/json',
        'User-Agent': 'growth-sync',
      },
      body: JSON.stringify(args),
    });
    if (!response.ok) {
      throw new Error(`GitHub API ${response.status} posting check run`);
    }
    return response.json();
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

  const apiUrl = process.env.GITHUB_API_URL ?? 'https://api.github.com';
  const getCommitMessage = makeGitHubClient({
    apiUrl,
    repository: GITHUB_REPOSITORY,
    token: githubToken,
    fetchFn: fetch,
  });
  const postCheckRun = makeCheckRunPoster({
    apiUrl,
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
    postCheckRun,
    attributionCheckAdvisory: process.env.GROWTH_ATTRIBUTION_CHECK === 'advisory',
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
