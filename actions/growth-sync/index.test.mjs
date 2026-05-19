import assert from 'node:assert/strict';
import { test } from 'node:test';
import { deflateRawSync } from 'node:zlib';

import {
  buildAttributionCheckArgs,
  buildCheckRunArgs,
  buildDeliveryLinkArgs,
  buildDeploymentArgs,
  buildGalleryComment,
  buildReleaseArgs,
  buildWorkflowRunArgs,
  encodeAnnotation,
  extractZipFiles,
  findGalleryComment,
  groupEvidenceFiles,
  isForkPullRequest,
  mapDeploymentState,
  parseBranchReference,
  parseTrailer,
  resolveCheckRunPullRequest,
  resolveCommitSha,
  resolveEventBranch,
  resolveWorkflowRunPullRequest,
  run,
} from './index.mjs';

test('parseTrailer reads a Growth-Work-Item trailer', () => {
  const message = 'Add widget\n\nLonger body.\n\nGrowth-Work-Item: 01HXYZ';
  assert.equal(parseTrailer(message), '01HXYZ');
});

test('parseTrailer returns null when no trailer is present', () => {
  assert.equal(parseTrailer('Just a commit message'), null);
  assert.equal(parseTrailer(''), null);
  assert.equal(parseTrailer(undefined), null);
});

test('parseTrailer takes the last trailer when several are present', () => {
  const message = 'Squash\n\nGrowth-Work-Item: 01OLD\nGrowth-Work-Item: 01NEW';
  assert.equal(parseTrailer(message), '01NEW');
});

test('resolveCommitSha uses the head sha for opened and synchronize', () => {
  const event = { action: 'opened', pull_request: { head: { sha: 'headsha' } } };
  assert.equal(resolveCommitSha(event), 'headsha');
  assert.equal(resolveCommitSha({ ...event, action: 'synchronize' }), 'headsha');
});

test('resolveCommitSha uses the merge commit for a merged PR', () => {
  const event = {
    action: 'closed',
    pull_request: { merged: true, merge_commit_sha: 'mergesha', head: { sha: 'headsha' } },
  };
  assert.equal(resolveCommitSha(event), 'mergesha');
});

test('resolveCommitSha returns null for a PR closed without merging', () => {
  const event = { action: 'closed', pull_request: { merged: false, head: { sha: 'headsha' } } };
  assert.equal(resolveCommitSha(event), null);
});

test('buildDeliveryLinkArgs produces an idempotent pull_request ref', () => {
  const event = {
    pull_request: { number: 42, html_url: 'https://github.com/o/r/pull/42', title: 'Add widget' },
  };
  assert.deepEqual(buildDeliveryLinkArgs(event, '01HXYZ'), {
    work_item_id: '01HXYZ',
    type: 'pull_request',
    ref: '#42',
    url: 'https://github.com/o/r/pull/42',
    description: 'GitHub pull request: Add widget',
  });
});

function silentLog() {
  return { info: () => {}, warn: () => {}, error: () => {} };
}

test('run records a delivery link on the happy path', async () => {
  const calls = [];
  const result = await run({
    eventName: 'pull_request',
    event: {
      action: 'synchronize',
      pull_request: { number: 7, html_url: 'u', title: 't', head: { sha: 'sha7' } },
    },
    getCommitMessage: async (sha) => {
      assert.equal(sha, 'sha7');
      return 'Work\n\nGrowth-Work-Item: 01WI';
    },
    callTool: async (name, args) => {
      calls.push({ name, args });
      return { isError: false, structured: { id: 'link1' } };
    },
    log: silentLog(),
  });

  assert.equal(result.skipped, false);
  assert.equal(calls.length, 1);
  assert.equal(calls[0].name, 'upsert-delivery-link');
  assert.equal(calls[0].args.work_item_id, '01WI');
  assert.equal(calls[0].args.ref, '#7');
});

test('run records an unattributed event when no trailer is present', async () => {
  const calls = [];
  const result = await run({
    eventName: 'pull_request',
    event: { action: 'opened', pull_request: { number: 1, html_url: 'u', head: { sha: 's' } } },
    repository: 'datashaman/growth',
    getCommitMessage: async () => 'No trailer here',
    callTool: async (name, args) => {
      calls.push({ name, args });
      return { isError: false };
    },
    log: silentLog(),
  });

  assert.equal(result.skipped, true);
  const record = calls.find((c) => c.name === 'record-unattributed-event');
  assert.ok(record, 'expected the unattributed event to be recorded');
  assert.equal(record.args.reason, 'missing_link');
  assert.equal(record.args.commit_sha, 's');
  assert.ok(!calls.some((c) => c.name === 'upsert-delivery-link'));
});

test('run skips a PR closed without merging without fetching a commit', async () => {
  let fetched = false;
  const result = await run({
    eventName: 'pull_request',
    event: { action: 'closed', pull_request: { merged: false, head: { sha: 's' } } },
    getCommitMessage: async () => {
      fetched = true;
      return '';
    },
    callTool: async () => ({ isError: false }),
    log: silentLog(),
  });

  assert.equal(result.skipped, true);
  assert.equal(fetched, false);
});

test('run throws when Growth rejects the delivery link', async () => {
  await assert.rejects(
    run({
      eventName: 'pull_request',
      event: {
        action: 'synchronize',
        pull_request: { number: 9, html_url: 'u', title: 't', head: { sha: 's' } },
      },
      getCommitMessage: async () => 'Growth-Work-Item: 01MISSING',
      callTool: async () => ({ isError: true, errorText: 'work item not found' }),
      log: silentLog(),
    }),
    /work item not found/,
  );
});

test('resolveCheckRunPullRequest returns the first PR or null', () => {
  assert.deepEqual(
    resolveCheckRunPullRequest({ check_run: { pull_requests: [{ number: 3 }, { number: 4 }] } }),
    { number: 3 },
  );
  assert.equal(resolveCheckRunPullRequest({ check_run: { pull_requests: [] } }), null);
  assert.equal(resolveCheckRunPullRequest({ check_run: {} }), null);
});

test('buildCheckRunArgs maps a check_run payload to upsert-check-run arguments', () => {
  const event = {
    check_run: {
      id: 555,
      name: 'tests',
      status: 'completed',
      conclusion: 'success',
      html_url: 'https://github.com/o/r/runs/555',
      started_at: '2026-01-01T00:00:00Z',
      completed_at: '2026-01-01T00:05:00Z',
    },
  };
  assert.deepEqual(buildCheckRunArgs(event, 'link1'), {
    work_item_delivery_link_id: 'link1',
    provider: 'github-actions',
    name: 'tests',
    run_ref: '555',
    status: 'completed',
    conclusion: 'success',
    url: 'https://github.com/o/r/runs/555',
    started_at: '2026-01-01T00:00:00Z',
    completed_at: '2026-01-01T00:05:00Z',
  });
});

function checkRunEvent() {
  return {
    action: 'completed',
    check_run: {
      id: 555,
      name: 'tests',
      status: 'completed',
      conclusion: 'success',
      html_url: 'https://github.com/o/r/runs/555',
      head_sha: 'crsha',
      pull_requests: [{ number: 7 }],
    },
  };
}

test('run records a check run against the PR delivery link on the happy path', async () => {
  const calls = [];
  const result = await run({
    eventName: 'check_run',
    event: checkRunEvent(),
    repository: 'datashaman/growth',
    getCommitMessage: async (sha) => {
      assert.equal(sha, 'crsha');
      return 'Work\n\nGrowth-Work-Item: 01WI';
    },
    callTool: async (name, args) => {
      calls.push({ name, args });
      return name === 'upsert-delivery-link'
        ? { isError: false, structured: { id: 'link1' } }
        : { isError: false, structured: { id: 'check1' } };
    },
    log: silentLog(),
  });

  assert.equal(result.skipped, false);
  assert.deepEqual(calls.map((c) => c.name), ['upsert-delivery-link', 'upsert-check-run']);
  assert.equal(calls[0].args.ref, '#7');
  assert.equal(calls[0].args.url, 'https://github.com/datashaman/growth/pull/7');
  assert.equal(calls[1].args.work_item_delivery_link_id, 'link1');
  assert.equal(calls[1].args.conclusion, 'success');
});

test('run skips a check run with no associated pull request', async () => {
  let called = false;
  const event = checkRunEvent();
  event.check_run.pull_requests = [];

  const result = await run({
    eventName: 'check_run',
    event,
    repository: 'datashaman/growth',
    getCommitMessage: async () => 'Growth-Work-Item: 01WI',
    callTool: async () => {
      called = true;
      return { isError: false };
    },
    log: silentLog(),
  });

  assert.equal(result.skipped, true);
  assert.equal(called, false);
});

test('run records an unattributed event for a check run with no trailer', async () => {
  const calls = [];
  const result = await run({
    eventName: 'check_run',
    event: checkRunEvent(),
    repository: 'datashaman/growth',
    getCommitMessage: async () => 'No trailer',
    callTool: async (name, args) => {
      calls.push({ name, args });
      return { isError: false };
    },
    log: silentLog(),
  });

  assert.equal(result.skipped, true);
  const record = calls.find((c) => c.name === 'record-unattributed-event');
  assert.ok(record, 'expected the unattributed event to be recorded');
  assert.equal(record.args.event_type, 'check_run');
  assert.equal(record.args.commit_sha, 'crsha');
  assert.ok(!calls.some((c) => c.name === 'upsert-check-run'));
});

test('run throws when Growth rejects the check run', async () => {
  await assert.rejects(
    run({
      eventName: 'check_run',
      event: checkRunEvent(),
      repository: 'datashaman/growth',
      getCommitMessage: async () => 'Growth-Work-Item: 01WI',
      callTool: async (name) =>
        name === 'upsert-delivery-link'
          ? { isError: false, structured: { id: 'link1' } }
          : { isError: true, errorText: 'bad conclusion' },
      log: silentLog(),
    }),
    /bad conclusion/,
  );
});

test('mapDeploymentState maps GitHub states to Growth statuses', () => {
  assert.equal(mapDeploymentState('success'), 'succeeded');
  assert.equal(mapDeploymentState('failure'), 'failed');
  assert.equal(mapDeploymentState('error'), 'failed');
  assert.equal(mapDeploymentState('pending'), null);
  assert.equal(mapDeploymentState('in_progress'), null);
});

test('buildDeploymentArgs maps a deployment_status payload to upsert-deployment arguments', () => {
  const event = {
    deployment: { id: 999, environment: 'production' },
    deployment_status: {
      state: 'success',
      environment: 'production',
      environment_url: 'https://app.example.com',
      created_at: '2026-01-01T00:00:00Z',
    },
  };
  assert.deepEqual(buildDeploymentArgs(event, 'proj1', 'succeeded'), {
    project_id: 'proj1',
    environment: 'production',
    status: 'succeeded',
    provider: 'github',
    external_ref: '999',
    url: 'https://app.example.com',
    deployed_at: '2026-01-01T00:00:00Z',
  });
});

function deploymentEvent() {
  return {
    action: 'created',
    deployment: { id: 999, environment: 'production' },
    deployment_status: { state: 'success', environment: 'production' },
  };
}

test('run records a deployment against the resolved project on the happy path', async () => {
  const calls = [];
  const result = await run({
    eventName: 'deployment_status',
    event: deploymentEvent(),
    repository: 'datashaman/growth',
    callTool: async (name, args) => {
      calls.push({ name, args });
      return name === 'resolve-project-by-repo'
        ? { isError: false, structured: { found: true, project_id: 'proj1' } }
        : { isError: false, structured: { id: 'dep1' } };
    },
    log: silentLog(),
  });

  assert.equal(result.skipped, false);
  assert.deepEqual(calls.map((c) => c.name), ['resolve-project-by-repo', 'upsert-deployment']);
  assert.equal(calls[0].args.github_repo, 'datashaman/growth');
  assert.equal(calls[1].args.project_id, 'proj1');
  assert.equal(calls[1].args.status, 'succeeded');
  assert.equal(calls[1].args.external_ref, '999');
});

test('run skips a deployment state Growth does not record', async () => {
  let called = false;
  const event = deploymentEvent();
  event.deployment_status.state = 'pending';

  const result = await run({
    eventName: 'deployment_status',
    event,
    repository: 'datashaman/growth',
    callTool: async () => {
      called = true;
      return { isError: false };
    },
    log: silentLog(),
  });

  assert.equal(result.skipped, true);
  assert.equal(called, false);
});

test('run skips a deployment whose repo has no bound project', async () => {
  const result = await run({
    eventName: 'deployment_status',
    event: deploymentEvent(),
    repository: 'datashaman/growth',
    callTool: async () => ({ isError: false, structured: { found: false, project_id: null } }),
    log: silentLog(),
  });

  assert.equal(result.skipped, true);
});

test('resolveWorkflowRunPullRequest returns the first PR or null', () => {
  assert.deepEqual(
    resolveWorkflowRunPullRequest({ workflow_run: { pull_requests: [{ number: 8 }, { number: 9 }] } }),
    { number: 8 },
  );
  assert.equal(resolveWorkflowRunPullRequest({ workflow_run: { pull_requests: [] } }), null);
  assert.equal(resolveWorkflowRunPullRequest({ workflow_run: {} }), null);
});

test('buildWorkflowRunArgs maps a workflow_run payload to upsert-check-run arguments', () => {
  const event = {
    workflow_run: {
      id: 777,
      name: 'CI',
      status: 'completed',
      conclusion: 'success',
      html_url: 'https://github.com/o/r/actions/runs/777',
      run_started_at: '2026-01-01T00:00:00Z',
      updated_at: '2026-01-01T00:06:00Z',
    },
  };
  assert.deepEqual(buildWorkflowRunArgs(event, 'link1'), {
    work_item_delivery_link_id: 'link1',
    provider: 'github-actions',
    name: 'CI',
    run_ref: '777',
    status: 'completed',
    conclusion: 'success',
    url: 'https://github.com/o/r/actions/runs/777',
    started_at: '2026-01-01T00:00:00Z',
    completed_at: '2026-01-01T00:06:00Z',
  });
});

function workflowRunEvent() {
  return {
    action: 'completed',
    workflow_run: {
      id: 777,
      name: 'CI',
      status: 'completed',
      conclusion: 'success',
      html_url: 'https://github.com/o/r/actions/runs/777',
      head_sha: 'wrsha',
      head_repository: { full_name: 'fork/repo' },
      pull_requests: [{ number: 8 }],
    },
  };
}

test('run records a workflow run against the PR delivery link on the happy path', async () => {
  const calls = [];
  const result = await run({
    eventName: 'workflow_run',
    event: workflowRunEvent(),
    repository: 'datashaman/growth',
    getCommitMessage: async (sha, repositoryOverride) => {
      assert.equal(sha, 'wrsha');
      assert.equal(repositoryOverride, 'fork/repo');
      return 'Work\n\nGrowth-Work-Item: 01WI';
    },
    callTool: async (name, args) => {
      calls.push({ name, args });
      return name === 'upsert-delivery-link'
        ? { isError: false, structured: { id: 'link1' } }
        : { isError: false, structured: { id: 'check1' } };
    },
    log: silentLog(),
  });

  assert.equal(result.skipped, false);
  assert.deepEqual(calls.map((c) => c.name), ['upsert-delivery-link', 'upsert-check-run']);
  assert.equal(calls[0].args.ref, '#8');
  assert.equal(calls[0].args.url, 'https://github.com/datashaman/growth/pull/8');
  assert.equal(calls[1].args.work_item_delivery_link_id, 'link1');
  assert.equal(calls[1].args.conclusion, 'success');
});

test('run skips a workflow run with no associated pull request', async () => {
  let called = false;
  const event = workflowRunEvent();
  event.workflow_run.pull_requests = [];

  const result = await run({
    eventName: 'workflow_run',
    event,
    repository: 'datashaman/growth',
    getCommitMessage: async () => 'Growth-Work-Item: 01WI',
    callTool: async () => {
      called = true;
      return { isError: false };
    },
    log: silentLog(),
  });

  assert.equal(result.skipped, true);
  assert.equal(called, false);
});

test('run records an unattributed event for a workflow run with no trailer', async () => {
  const calls = [];
  const result = await run({
    eventName: 'workflow_run',
    event: workflowRunEvent(),
    repository: 'datashaman/growth',
    getCommitMessage: async () => 'No trailer',
    callTool: async (name, args) => {
      calls.push({ name, args });
      return { isError: false };
    },
    log: silentLog(),
  });

  assert.equal(result.skipped, true);
  const record = calls.find((c) => c.name === 'record-unattributed-event');
  assert.ok(record, 'expected the unattributed event to be recorded');
  assert.equal(record.args.event_type, 'workflow_run');
  assert.ok(!calls.some((c) => c.name === 'upsert-check-run'));
});

test('buildReleaseArgs maps a release payload to upsert-release arguments', () => {
  const event = {
    release: {
      tag_name: 'v1.2.3',
      name: 'Spring release',
      body: 'Notes here',
      published_at: '2026-01-01T00:00:00Z',
    },
  };
  assert.deepEqual(buildReleaseArgs(event, 'proj1'), {
    project_id: 'proj1',
    version: 'v1.2.3',
    name: 'Spring release',
    status: 'released',
    released_at: '2026-01-01T00:00:00Z',
    notes: 'Notes here',
  });
});

function releaseEvent() {
  return {
    action: 'published',
    release: { tag_name: 'v1.2.3', name: 'Spring release', published_at: '2026-01-01T00:00:00Z' },
  };
}

test('run records a release against the resolved project on the happy path', async () => {
  const calls = [];
  const result = await run({
    eventName: 'release',
    event: releaseEvent(),
    repository: 'datashaman/growth',
    callTool: async (name, args) => {
      calls.push({ name, args });
      return name === 'resolve-project-by-repo'
        ? { isError: false, structured: { found: true, project_id: 'proj1' } }
        : { isError: false, structured: { id: 'rel1' } };
    },
    log: silentLog(),
  });

  assert.equal(result.skipped, false);
  assert.deepEqual(calls.map((c) => c.name), ['resolve-project-by-repo', 'upsert-release']);
  assert.equal(calls[0].args.github_repo, 'datashaman/growth');
  assert.equal(calls[1].args.project_id, 'proj1');
  assert.equal(calls[1].args.version, 'v1.2.3');
  assert.equal(calls[1].args.status, 'released');
});

test('run skips a release whose repo has no bound project', async () => {
  const result = await run({
    eventName: 'release',
    event: releaseEvent(),
    repository: 'datashaman/growth',
    callTool: async () => ({ isError: false, structured: { found: false, project_id: null } }),
    log: silentLog(),
  });

  assert.equal(result.skipped, true);
});

test('run skips an unhandled event type', async () => {
  const result = await run({
    eventName: 'push',
    event: {},
    getCommitMessage: async () => '',
    callTool: async () => ({ isError: false }),
    log: silentLog(),
  });

  assert.equal(result.skipped, true);
});

test('resolveEventBranch reads the branch from each event type', () => {
  assert.equal(
    resolveEventBranch('pull_request', { pull_request: { head: { ref: 'feature/a' } } }),
    'feature/a',
  );
  assert.equal(
    resolveEventBranch('check_run', { check_run: {} }, { head: { ref: 'feature/b' } }),
    'feature/b',
  );
  assert.equal(
    resolveEventBranch('check_run', { check_run: { check_suite: { head_branch: 'feature/c' } } }),
    'feature/c',
  );
  assert.equal(
    resolveEventBranch('workflow_run', { workflow_run: { head_branch: 'feature/d' } }),
    'feature/d',
  );
  assert.equal(resolveEventBranch('release', {}), null);
});

test('run binds the branch when a pull request commit carries a trailer', async () => {
  const calls = [];
  const result = await run({
    eventName: 'pull_request',
    event: {
      action: 'synchronize',
      pull_request: { number: 7, html_url: 'u', title: 't', head: { sha: 'sha7', ref: 'feature/lander' } },
    },
    repository: 'datashaman/growth',
    getCommitMessage: async () => 'Work\n\nGrowth-Work-Item: 01WI',
    callTool: async (name, args) => {
      calls.push({ name, args });
      return { isError: false, structured: { id: 'link1' } };
    },
    log: silentLog(),
  });

  assert.equal(result.skipped, false);
  const branchCall = calls.find((c) => c.name === 'upsert-delivery-link' && c.args.type === 'branch');
  assert.ok(branchCall, 'expected a branch delivery link to be recorded');
  assert.equal(branchCall.args.work_item_id, '01WI');
  assert.equal(branchCall.args.ref, 'feature/lander');
  assert.ok(calls.some((c) => c.name === 'upsert-delivery-link' && c.args.type === 'pull_request'));
});

test('run url-encodes a branch name with reserved characters in the binding', async () => {
  const calls = [];
  await run({
    eventName: 'pull_request',
    event: {
      action: 'synchronize',
      pull_request: { number: 7, html_url: 'u', title: 't', head: { sha: 'sha7', ref: 'issue#123/fix' } },
    },
    repository: 'datashaman/growth',
    getCommitMessage: async () => 'Work\n\nGrowth-Work-Item: 01WI',
    callTool: async (name, args) => {
      calls.push({ name, args });
      return { isError: false, structured: { id: 'link1' } };
    },
    log: silentLog(),
  });

  const branchCall = calls.find((c) => c.name === 'upsert-delivery-link' && c.args.type === 'branch');
  assert.ok(branchCall, 'expected a branch delivery link to be recorded');
  assert.equal(branchCall.args.ref, 'issue#123/fix');
  assert.equal(branchCall.args.url, 'https://github.com/datashaman/growth/tree/issue%23123/fix');
});

test('run skips a trailer-less event whose branch is ambiguously bound', async () => {
  const warnings = [];
  const calls = [];
  const result = await run({
    eventName: 'check_run',
    event: {
      check_run: {
        head_sha: 'shaX',
        name: 'tests',
        pull_requests: [{ number: 9, head: { ref: 'feature/contested' } }],
      },
    },
    repository: 'datashaman/growth',
    getCommitMessage: async () => 'No trailer',
    callTool: async (name, args) => {
      calls.push({ name, args });
      if (name === 'resolve-work-item-by-branch') {
        return { isError: false, structured: { found: false, ambiguous: true, work_item_id: null } };
      }
      if (name === 'record-unattributed-event') {
        return { isError: false };
      }
      throw new Error(`unexpected call to ${name}`);
    },
    log: { ...silentLog(), warn: (m) => warnings.push(m) },
  });

  assert.equal(result.skipped, true);
  assert.ok(warnings.some((m) => m.includes('more than one work item')), 'expected an ambiguity warning');
  const record = calls.find((c) => c.name === 'record-unattributed-event');
  assert.ok(record, 'expected the unattributed event to be recorded');
  assert.equal(record.args.reason, 'ambiguous_branch');
});

test('run attributes a trailer-less check run via its branch binding', async () => {
  const calls = [];
  const result = await run({
    eventName: 'check_run',
    event: {
      check_run: {
        head_sha: 'shaX',
        name: 'tests',
        status: 'completed',
        conclusion: 'success',
        pull_requests: [{ number: 9, head: { ref: 'feature/lander' } }],
      },
    },
    repository: 'datashaman/growth',
    getCommitMessage: async () => 'No trailer on this commit',
    callTool: async (name, args) => {
      calls.push({ name, args });
      if (name === 'resolve-work-item-by-branch') {
        return { isError: false, structured: { found: true, work_item_id: '01BR' } };
      }
      return { isError: false, structured: { id: 'link1' } };
    },
    log: silentLog(),
  });

  assert.equal(result.skipped, false);
  const lookup = calls.find((c) => c.name === 'resolve-work-item-by-branch');
  assert.ok(lookup, 'expected a branch lookup');
  assert.equal(lookup.args.branch, 'feature/lander');
  assert.equal(lookup.args.github_repo, 'datashaman/growth');
  assert.ok(calls.some((c) => c.name === 'upsert-delivery-link' && c.args.work_item_id === '01BR'));
  assert.ok(calls.some((c) => c.name === 'upsert-check-run'));
});

test('run skips a trailer-less event whose branch is not bound', async () => {
  const result = await run({
    eventName: 'check_run',
    event: {
      check_run: {
        head_sha: 'shaX',
        name: 'tests',
        pull_requests: [{ number: 9, head: { ref: 'feature/orphan' } }],
      },
    },
    repository: 'datashaman/growth',
    getCommitMessage: async () => 'No trailer',
    callTool: async (name) => {
      if (name === 'resolve-work-item-by-branch') {
        return { isError: false, structured: { found: false, work_item_id: null } };
      }
      if (name === 'record-unattributed-event') {
        return { isError: false };
      }
      throw new Error(`unexpected call to ${name}`);
    },
    log: silentLog(),
  });

  assert.equal(result.skipped, true);
});

test('parseBranchReference extracts a WI-NNN reference from any position', () => {
  assert.equal(parseBranchReference('WI-42-add-login'), 'WI-42');
  assert.equal(parseBranchReference('feature/WI-42'), 'WI-42');
  assert.equal(parseBranchReference('marlin/wi-7-fix'), 'WI-7');
  assert.equal(parseBranchReference('WI-009'), 'WI-9');
  assert.equal(parseBranchReference('WI-42'), 'WI-42');
});

test('parseBranchReference returns null when no reference is present', () => {
  assert.equal(parseBranchReference('feature/add-login'), null);
  assert.equal(parseBranchReference('fixWI-42'), null);
  assert.equal(parseBranchReference('WI-x'), null);
  assert.equal(parseBranchReference(''), null);
  assert.equal(parseBranchReference(null), null);
  assert.equal(parseBranchReference(undefined), null);
});

test('run attributes a trailer-less pull request via a WI-NNN branch reference', async () => {
  const calls = [];
  const result = await run({
    eventName: 'pull_request',
    event: {
      action: 'synchronize',
      pull_request: {
        number: 12,
        html_url: 'https://github.com/datashaman/growth/pull/12',
        head: { ref: 'WI-42-add-login', sha: 'headsha', repo: { full_name: 'datashaman/growth' } },
      },
    },
    repository: 'datashaman/growth',
    getCommitMessage: async () => 'No trailer here',
    callTool: async (name, args) => {
      calls.push({ name, args });
      if (name === 'resolve-work-item-by-reference') {
        return { isError: false, structured: { found: true, work_item_id: '01REF' } };
      }
      return { isError: false, structured: { id: 'link1' } };
    },
    log: silentLog(),
  });

  assert.equal(result.skipped, false);
  const lookup = calls.find((c) => c.name === 'resolve-work-item-by-reference');
  assert.ok(lookup, 'expected a reference lookup');
  assert.equal(lookup.args.reference, 'WI-42');
  assert.equal(lookup.args.github_repo, 'datashaman/growth');
  assert.ok(calls.some((c) => c.name === 'upsert-delivery-link'
    && c.args.type === 'pull_request' && c.args.work_item_id === '01REF'));
  assert.ok(!calls.some((c) => c.name === 'upsert-delivery-link' && c.args.type === 'branch'),
    'a branch reference must not record a branch delivery link');
});

test('run resolves a branch reference for a fork pull request', async () => {
  const calls = [];
  const result = await run({
    eventName: 'pull_request',
    event: {
      action: 'synchronize',
      pull_request: {
        number: 13,
        html_url: 'https://github.com/datashaman/growth/pull/13',
        head: { ref: 'WI-7-fix', sha: 'forksha', repo: { full_name: 'someone/growth-fork' } },
      },
    },
    repository: 'datashaman/growth',
    getCommitMessage: async () => 'No trailer here',
    callTool: async (name, args) => {
      calls.push({ name, args });
      if (name === 'resolve-work-item-by-reference') {
        return { isError: false, structured: { found: true, work_item_id: '01FORK' } };
      }
      return { isError: false, structured: { id: 'link1' } };
    },
    log: silentLog(),
  });

  assert.equal(result.skipped, false);
  assert.ok(calls.some((c) => c.name === 'resolve-work-item-by-reference'));
  assert.ok(!calls.some((c) => c.name === 'resolve-work-item-by-branch'),
    'a branch reference should not fall through to the branch binding');
  assert.ok(!calls.some((c) => c.name === 'upsert-delivery-link' && c.args.type === 'branch'),
    'a branch reference must not record a branch delivery link');
});

test('a branch reference wins over a colliding branch delivery-link binding', async () => {
  const calls = [];
  await run({
    eventName: 'check_run',
    event: {
      check_run: {
        head_sha: 'shaX',
        name: 'tests',
        status: 'completed',
        conclusion: 'success',
        pull_requests: [{ number: 9, head: { ref: 'WI-42-add-login' } }],
      },
    },
    repository: 'datashaman/growth',
    getCommitMessage: async () => 'No trailer',
    callTool: async (name, args) => {
      calls.push({ name, args });
      if (name === 'resolve-work-item-by-reference') {
        return { isError: false, structured: { found: true, work_item_id: '01REF' } };
      }
      if (name === 'resolve-work-item-by-branch') {
        // The branch is also bound to a different work item; the reference
        // must take precedence and this lookup must not even be reached.
        return { isError: false, structured: { found: true, work_item_id: '01BRANCH' } };
      }
      return { isError: false, structured: { id: 'link1' } };
    },
    log: silentLog(),
  });

  assert.ok(!calls.some((c) => c.name === 'resolve-work-item-by-branch'),
    'the branch binding must not be consulted once a reference resolves');
  assert.ok(calls.some((c) => c.name === 'upsert-delivery-link'
    && c.args.type === 'pull_request' && c.args.work_item_id === '01REF'));
  assert.ok(!calls.some((c) => c.name === 'upsert-delivery-link' && c.args.type === 'branch'),
    'a branch reference must not record a branch delivery link');
  assert.ok(calls.some((c) => c.name === 'upsert-check-run'));
});

test('an unresolved branch reference falls through to the branch binding', async () => {
  const calls = [];
  const result = await run({
    eventName: 'check_run',
    event: {
      check_run: {
        head_sha: 'shaX',
        name: 'tests',
        status: 'completed',
        conclusion: 'success',
        pull_requests: [{ number: 9, head: { ref: 'WI-999-stale' } }],
      },
    },
    repository: 'datashaman/growth',
    getCommitMessage: async () => 'No trailer',
    callTool: async (name) => {
      calls.push({ name });
      if (name === 'resolve-work-item-by-reference') {
        return { isError: false, structured: { found: false, work_item_id: null } };
      }
      if (name === 'resolve-work-item-by-branch') {
        return { isError: false, structured: { found: true, work_item_id: '01BRANCH' } };
      }
      return { isError: false, structured: { id: 'link1' } };
    },
    log: silentLog(),
  });

  assert.equal(result.skipped, false);
  assert.ok(calls.some((c) => c.name === 'resolve-work-item-by-reference'));
  assert.ok(calls.some((c) => c.name === 'resolve-work-item-by-branch'));
});

test('buildAttributionCheckArgs passes when a work item resolved', () => {
  const args = buildAttributionCheckArgs({ headSha: 'sha1', workItemId: '01WI', branch: 'feature/x' });
  assert.equal(args.name, 'Growth: work item attribution');
  assert.equal(args.head_sha, 'sha1');
  assert.equal(args.status, 'completed');
  assert.equal(args.conclusion, 'success');
  assert.match(args.output.title, /01WI/);
});

test('buildAttributionCheckArgs fails when no work item resolved', () => {
  const args = buildAttributionCheckArgs({ headSha: 'sha1', workItemId: null, branch: 'feature/x' });
  assert.equal(args.conclusion, 'failure');
  assert.match(args.output.title, /No Growth work item/);
  assert.match(args.output.summary, /Growth-Work-Item/);
  assert.match(args.output.summary, /feature\/x/);
});

test('buildAttributionCheckArgs reports neutral in advisory mode', () => {
  const args = buildAttributionCheckArgs({ headSha: 'sha1', workItemId: null, advisory: true });
  assert.equal(args.conclusion, 'neutral');
});

test('run posts a passing attribution check when a pull request resolves', async () => {
  const checks = [];
  await run({
    eventName: 'pull_request',
    event: {
      action: 'synchronize',
      pull_request: {
        number: 7, html_url: 'u', title: 't',
        head: { sha: 'sha7', ref: 'feature/lander', repo: { full_name: 'datashaman/growth' } },
      },
    },
    repository: 'datashaman/growth',
    getCommitMessage: async () => 'Work\n\nGrowth-Work-Item: 01WI',
    callTool: async () => ({ isError: false, structured: { id: 'link1' } }),
    postCheckRun: async (args) => { checks.push(args); return {}; },
    log: silentLog(),
  });

  assert.equal(checks.length, 1);
  assert.equal(checks[0].conclusion, 'success');
  assert.equal(checks[0].head_sha, 'sha7');
});

test('run posts a failing attribution check when a pull request cannot be attributed', async () => {
  const checks = [];
  const result = await run({
    eventName: 'pull_request',
    event: {
      action: 'opened',
      pull_request: {
        number: 7, html_url: 'u', title: 't',
        head: { sha: 'sha7', ref: 'feature/orphan', repo: { full_name: 'datashaman/growth' } },
      },
    },
    repository: 'datashaman/growth',
    getCommitMessage: async () => 'No trailer here',
    callTool: async (name) => {
      if (name === 'resolve-work-item-by-branch') {
        return { isError: false, structured: { found: false, work_item_id: null } };
      }
      if (name === 'record-unattributed-event') {
        return { isError: false };
      }
      throw new Error(`unexpected call to ${name}`);
    },
    postCheckRun: async (args) => { checks.push(args); return {}; },
    log: silentLog(),
  });

  assert.equal(result.skipped, true);
  assert.equal(checks.length, 1);
  assert.equal(checks[0].conclusion, 'failure');
});

test('encodeAnnotation escapes percent, carriage returns, and newlines', () => {
  // Workflow commands are single-line; an unescaped newline truncates the
  // annotation or breaks the command parser.
  assert.equal(encodeAnnotation('a\nb%c'), 'a%0Ab%25c');
  assert.equal(encodeAnnotation('line1\r\nline2'), 'line1%0D%0Aline2');
  assert.equal(encodeAnnotation('plain text'), 'plain text');
});

test('run emits a loud error annotation when the attribution check POST is forbidden', async () => {
  const errors = [];
  const warnings = [];
  const result = await run({
    eventName: 'pull_request',
    event: {
      action: 'synchronize',
      pull_request: {
        number: 7, html_url: 'u', title: 't',
        head: { sha: 'sha7', ref: 'feature/lander', repo: { full_name: 'datashaman/growth' } },
      },
    },
    repository: 'datashaman/growth',
    getCommitMessage: async () => 'Work\n\nGrowth-Work-Item: 01WI',
    callTool: async () => ({ isError: false, structured: { id: 'link1' } }),
    postCheckRun: async () => {
      const error = new Error('GitHub API 403 posting check run');
      error.status = 403;
      throw error;
    },
    log: { ...silentLog(), error: (m) => errors.push(m), warn: (m) => warnings.push(m) },
  });

  // The delivery link is still recorded; only the check post failed.
  assert.equal(result.skipped, false);
  assert.equal(errors.length, 1, 'expected one loud error annotation');
  assert.match(errors[0], /checks: write/);
  assert.equal(warnings.length, 0, 'a 403 must not be downgraded to a warning');
});

test('run keeps a non-permission attribution check failure as a warning', async () => {
  const errors = [];
  const warnings = [];
  await run({
    eventName: 'pull_request',
    event: {
      action: 'synchronize',
      pull_request: {
        number: 7, html_url: 'u', title: 't',
        head: { sha: 'sha7', ref: 'feature/lander', repo: { full_name: 'datashaman/growth' } },
      },
    },
    repository: 'datashaman/growth',
    getCommitMessage: async () => 'Work\n\nGrowth-Work-Item: 01WI',
    callTool: async () => ({ isError: false, structured: { id: 'link1' } }),
    postCheckRun: async () => {
      const error = new Error('GitHub API 500 posting check run');
      error.status = 500;
      throw error;
    },
    log: { ...silentLog(), error: (m) => errors.push(m), warn: (m) => warnings.push(m) },
  });

  assert.equal(errors.length, 0, 'a transient failure is not a loud annotation');
  assert.ok(warnings.some((m) => /Could not post the attribution check/.test(m)));
});

test('run reports a neutral attribution check in advisory mode', async () => {
  const checks = [];
  await run({
    eventName: 'pull_request',
    event: {
      action: 'opened',
      pull_request: {
        number: 7, html_url: 'u', title: 't',
        head: { sha: 'sha7', ref: 'feature/orphan', repo: { full_name: 'datashaman/growth' } },
      },
    },
    repository: 'datashaman/growth',
    attributionCheckAdvisory: true,
    getCommitMessage: async () => 'No trailer here',
    callTool: async () => ({ isError: false, structured: { found: false, work_item_id: null } }),
    postCheckRun: async (args) => { checks.push(args); return {}; },
    log: silentLog(),
  });

  assert.equal(checks.length, 1);
  assert.equal(checks[0].conclusion, 'neutral');
});

test('run skips the attribution check for a fork pull request but still records the link', async () => {
  const checks = [];
  const calls = [];
  await run({
    eventName: 'pull_request',
    event: {
      action: 'opened',
      pull_request: {
        number: 7, html_url: 'u', title: 't',
        head: { sha: 'sha7', ref: 'feature/x', repo: { full_name: 'contributor/growth' } },
      },
    },
    repository: 'datashaman/growth',
    getCommitMessage: async () => 'Work\n\nGrowth-Work-Item: 01WI',
    callTool: async (name, args) => { calls.push({ name, args }); return { isError: false, structured: { id: 'link1' } }; },
    postCheckRun: async (args) => { checks.push(args); return {}; },
    log: silentLog(),
  });

  assert.equal(checks.length, 0);
  assert.ok(
    calls.some((c) => c.name === 'upsert-delivery-link' && c.args.type === 'pull_request'),
    'expected the fork PR delivery link to still be recorded',
  );
  assert.ok(
    !calls.some((c) => c.name === 'upsert-delivery-link' && c.args.type === 'branch'),
    'a fork branch must not become a base-repo branch binding',
  );
});

test('isForkPullRequest is true only when the head repo differs from the base', () => {
  assert.equal(
    isForkPullRequest({ head: { repo: { full_name: 'contributor/growth' } } }, 'datashaman/growth'),
    true,
  );
  assert.equal(
    isForkPullRequest({ head: { repo: { full_name: 'datashaman/growth' } } }, 'datashaman/growth'),
    false,
  );
  // A missing head repo is treated as same-repo, matching the check guard.
  assert.equal(isForkPullRequest({ head: {} }, 'datashaman/growth'), false);
});

test('run does not resolve a fork pull request via a base-repo branch binding', async () => {
  // The collision: another fork could have bound `feature/shared` in the
  // base repo. This fork's trailer-less commit must not borrow that binding.
  const calls = [];
  const result = await run({
    eventName: 'pull_request',
    event: {
      action: 'opened',
      pull_request: {
        number: 7, html_url: 'u', title: 't',
        head: { sha: 'sha7', ref: 'feature/shared', repo: { full_name: 'contributor/growth' } },
      },
    },
    repository: 'datashaman/growth',
    getCommitMessage: async () => 'No trailer on this fork commit',
    callTool: async (name, args) => {
      if (name === 'resolve-work-item-by-branch') {
        throw new Error('a fork PR must not consult a base-repo branch binding');
      }
      calls.push({ name, args });
      return { isError: false };
    },
    log: silentLog(),
  });

  assert.equal(result.skipped, true);
  const record = calls.find((c) => c.name === 'record-unattributed-event');
  assert.ok(record, 'expected the fork event to be recorded as unattributed');
  assert.equal(record.args.reason, 'missing_link');
});

test('run does not post an attribution check on a closed pull request', async () => {
  const checks = [];
  await run({
    eventName: 'pull_request',
    event: {
      action: 'closed',
      pull_request: {
        number: 7, html_url: 'u', title: 't', merged: true, merge_commit_sha: 'merge7',
        head: { sha: 'sha7', ref: 'feature/x', repo: { full_name: 'datashaman/growth' } },
      },
    },
    repository: 'datashaman/growth',
    getCommitMessage: async () => 'Work\n\nGrowth-Work-Item: 01WI',
    callTool: async () => ({ isError: false, structured: { id: 'link1' } }),
    postCheckRun: async (args) => { checks.push(args); return {}; },
    log: silentLog(),
  });

  assert.equal(checks.length, 0);
});

test('run ignores its own attribution check run without further calls', async () => {
  const result = await run({
    eventName: 'check_run',
    event: {
      check_run: {
        name: 'Growth: work item attribution',
        head_sha: 'sha7',
        pull_requests: [{ number: 9, head: { ref: 'feature/x' } }],
      },
    },
    repository: 'datashaman/growth',
    getCommitMessage: async () => { throw new Error('should not fetch a commit'); },
    callTool: async () => { throw new Error('should not call a tool'); },
    log: silentLog(),
  });

  assert.equal(result.skipped, true);
});

// --- Visual evidence ingestion -------------------------------------------

/**
 * Build a real ZIP buffer with local file headers, entry data, and a central
 * directory — enough for extractZipFiles, which follows local headers to
 * inflate each entry. Each entry is `{ name, bytes?, deflate? }`: a name
 * ending in `/` is a directory (no data); `deflate: true` stores the bytes
 * DEFLATE-compressed (method 8) rather than stored (method 0).
 */
function makeZipWithData(entries) {
  const localParts = [];
  const centralParts = [];
  let offset = 0;
  for (const entry of entries) {
    const nameBuffer = Buffer.from(entry.name, 'utf8');
    const raw = entry.bytes ?? Buffer.alloc(0);
    const deflate = entry.deflate === true;
    const stored = deflate ? deflateRawSync(raw) : raw;
    const method = deflate ? 8 : 0;

    const local = Buffer.alloc(30);
    local.writeUInt32LE(0x04034b50, 0);
    local.writeUInt16LE(method, 8);
    local.writeUInt32LE(stored.length, 18);
    local.writeUInt32LE(raw.length, 22);
    local.writeUInt16LE(nameBuffer.length, 26);
    const localRecord = Buffer.concat([local, nameBuffer, stored]);
    localParts.push(localRecord);

    const central = Buffer.alloc(46);
    central.writeUInt32LE(0x02014b50, 0);
    central.writeUInt16LE(method, 10);
    central.writeUInt32LE(stored.length, 20);
    // A crafted entry can declare a huge uncompressed size in the central
    // directory; `uncompressedSizeOverride` lets a test forge that.
    central.writeUInt32LE(entry.uncompressedSizeOverride ?? raw.length, 24);
    central.writeUInt16LE(nameBuffer.length, 28);
    central.writeUInt32LE(offset, 42);
    centralParts.push(Buffer.concat([central, nameBuffer]));

    offset += localRecord.length;
  }
  const localBlock = Buffer.concat(localParts);
  const centralBlock = Buffer.concat(centralParts);
  const eocd = Buffer.alloc(22);
  eocd.writeUInt32LE(0x06054b50, 0);
  eocd.writeUInt16LE(entries.length, 8);
  eocd.writeUInt16LE(entries.length, 10);
  eocd.writeUInt32LE(centralBlock.length, 12);
  eocd.writeUInt32LE(localBlock.length, 16);
  return Buffer.concat([localBlock, centralBlock, eocd]);
}

/** A ZIP of evidence screenshots, each entry's bytes a marker of its name. */
function evidenceZip(names, { deflate = false } = {}) {
  return makeZipWithData(names.map((name) => ({
    name,
    bytes: Buffer.from(`png-bytes-of-${name}`),
    deflate,
  })));
}

test('extractZipFiles inflates stored entries with their names and bytes', () => {
  const files = extractZipFiles(makeZipWithData([
    { name: 'dashboard/a.png', bytes: Buffer.from('alpha') },
    { name: 'plan/c.png', bytes: Buffer.from('charlie') },
  ]));
  assert.deepEqual(files.map((file) => file.name), ['dashboard/a.png', 'plan/c.png']);
  assert.equal(files[0].bytes.toString(), 'alpha');
  assert.equal(files[1].bytes.toString(), 'charlie');
});

test('extractZipFiles inflates DEFLATE-compressed entries', () => {
  const original = Buffer.from('a fairly repetitive payload '.repeat(20));
  const files = extractZipFiles(makeZipWithData([
    { name: 'dashboard/big.png', bytes: original, deflate: true },
  ]));
  assert.equal(files.length, 1);
  assert.deepEqual(files[0].bytes, original);
});

test('extractZipFiles skips directory entries', () => {
  const files = extractZipFiles(makeZipWithData([
    { name: 'dashboard/' },
    { name: 'dashboard/a.png', bytes: Buffer.from('alpha') },
  ]));
  assert.deepEqual(files.map((file) => file.name), ['dashboard/a.png']);
});

test('extractZipFiles throws on a buffer that is not a ZIP archive', () => {
  assert.throws(() => extractZipFiles(Buffer.from('not a zip archive at all')), /not a zip archive/i);
});

test('extractZipFiles rejects an entry that declares an oversized uncompressed size', () => {
  assert.throws(
    () => extractZipFiles(makeZipWithData([
      { name: 'dashboard/bomb.png', bytes: Buffer.from('tiny'), uncompressedSizeOverride: 256 * 1024 * 1024 },
    ])),
    /exceeding the .* evidence limit/,
  );
});

test('groupEvidenceFiles groups PNGs by top folder, dropping non-PNGs and root files', () => {
  const groups = groupEvidenceFiles([
    'plan/draft.png',
    'dashboard/b.png',
    'dashboard/a.png',
    'dashboard/notes.txt',
    'README.md',
    'loose.png',
  ]);
  assert.deepEqual(groups, [
    { folder: 'dashboard', files: ['dashboard/a.png', 'dashboard/b.png'] },
    { folder: 'plan', files: ['plan/draft.png'] },
  ]);
});

test('buildGalleryComment lists screenshots grouped by folder under the marker', () => {
  const body = buildGalleryComment({
    groups: [{ folder: 'dashboard', files: ['dashboard/a.png', 'dashboard/b.png'] }],
    artifactUrl: 'https://example.com/artifact',
  });
  assert.ok(findGalleryComment([{ body }]), 'the comment carries the gallery marker');
  assert.match(body, /2 screenshots/);
  assert.match(body, /### `dashboard`/);
  assert.match(body, /dashboard\/a\.png/);
  assert.match(body, /https:\/\/example\.com\/artifact/);
});

test('buildGalleryComment embeds images inline when given Growth-hosted assets', () => {
  const body = buildGalleryComment({
    groups: [{ folder: 'dashboard', files: ['dashboard/a.png', 'dashboard/b.png'] }],
    assets: new Map([
      ['dashboard/a.png', 'https://growth.test/evidence-assets/01A'],
      ['dashboard/b.png', 'https://growth.test/evidence-assets/01B'],
    ]),
  });
  assert.ok(findGalleryComment([{ body }]), 'the comment carries the gallery marker');
  assert.match(body, /2 screenshots/);
  assert.match(body, /### `dashboard`/);
  assert.match(body, /!\[dashboard\/a\.png\]\(https:\/\/growth\.test\/evidence-assets\/01A\)/);
  assert.match(body, /!\[dashboard\/b\.png\]\(https:\/\/growth\.test\/evidence-assets\/01B\)/);
  // The inline gallery has no artifact download link — the images are durable.
  assert.doesNotMatch(body, /Download the artifact/);
});

test('buildGalleryComment neutralises crafted folder and file names', () => {
  const body = buildGalleryComment({
    groups: [{ folder: 'evil`/@acme', files: ['evil`/@acme/shot\n.png'] }],
    artifactUrl: 'https://example.com/artifact',
  });
  // Backticks are replaced so they cannot terminate the code span, control
  // characters are stripped, and the name stays inside an inline code span so
  // an embedded @ never renders as a mention.
  assert.match(body, /### `evil'\/@acme`/);
  assert.match(body, /- `evil'\/@acme\/shot\.png`/);
});

test('findGalleryComment returns null when no comment carries the marker', () => {
  assert.equal(findGalleryComment([{ body: 'hello' }, { body: 'world' }]), null);
  assert.equal(findGalleryComment([]), null);
  assert.equal(findGalleryComment(undefined), null);
});

function evidenceContext(overrides = {}) {
  return {
    eventName: 'workflow_run',
    event: workflowRunEvent(),
    repository: 'datashaman/growth',
    getCommitMessage: async () => 'Work\n\nGrowth-Work-Item: 01WI',
    listRunArtifacts: async () => [{ id: 9, name: 'growth-evidence', expired: false }],
    downloadArtifact: async () => evidenceZip(['dashboard/empty.png', 'dashboard/full.png', 'plan/draft.png']),
    // The upload echoes each screenshot back as an asset with a Growth URL.
    uploadEvidence: async (deliveryLinkId, files) => files.map((file) => ({
      caption: file.name,
      url: `https://growth.test/evidence-assets/${deliveryLinkId}/${file.name}`,
    })),
    listIssueComments: async () => [],
    createComment: async (issueNumber, body) => ({
      id: 100,
      html_url: `https://github.com/datashaman/growth/pull/${issueNumber}#issuecomment-100`,
      body,
    }),
    updateComment: async (id, body) => ({ id, html_url: `https://example.com/c/${id}`, body }),
    log: silentLog(),
    ...overrides,
  };
}

test('run uploads screenshots, embeds them inline, and cites the gallery', async () => {
  const calls = [];
  const uploads = [];
  let created = 0;
  let createdBody = '';
  const result = await run(evidenceContext({
    callTool: async (name, args) => {
      calls.push({ name, args });
      return name === 'upsert-delivery-link'
        ? { isError: false, structured: { id: 'link1' } }
        : { isError: false, structured: { id: 'check1' } };
    },
    uploadEvidence: async (deliveryLinkId, files) => {
      uploads.push({ deliveryLinkId, files });
      return files.map((file) => ({
        caption: file.name,
        url: `https://growth.test/evidence-assets/${file.name}`,
      }));
    },
    createComment: async (issueNumber, body) => {
      created++;
      createdBody = body;
      return {
        id: 100,
        html_url: `https://github.com/datashaman/growth/pull/${issueNumber}#issuecomment-100`,
        body,
      };
    },
    updateComment: async () => { throw new Error('should not update a comment'); },
  }));

  assert.equal(result.skipped, false);
  assert.equal(created, 1);

  // One upload, scoped to the evidence delivery link, carrying every PNG with
  // its inflated bytes.
  assert.equal(uploads.length, 1, 'all screenshots go in a single upload');
  assert.equal(uploads[0].deliveryLinkId, 'link1');
  assert.deepEqual(
    uploads[0].files.map((file) => file.name),
    ['dashboard/empty.png', 'dashboard/full.png', 'plan/draft.png'],
  );
  assert.equal(uploads[0].files[0].bytes.toString(), 'png-bytes-of-dashboard/empty.png');

  // The comment embeds the Growth-hosted images inline.
  assert.match(createdBody, /!\[dashboard\/empty\.png\]\(https:\/\/growth\.test\/evidence-assets\/dashboard\/empty\.png\)/);

  // Two evidence-link upserts on the same ref: a minimal create before the
  // upload, then a final one carrying the posted comment's URL.
  const evidence = calls.filter((c) => c.name === 'upsert-delivery-link' && c.args.type === 'evidence');
  assert.equal(evidence.length, 2);
  assert.equal(evidence[0].args.ref, '#8');
  assert.equal(evidence[0].args.url, undefined, 'the first upsert precedes the comment');
  assert.equal(evidence[1].args.ref, '#8');
  assert.equal(evidence[1].args.url, 'https://github.com/datashaman/growth/pull/8#issuecomment-100');
  assert.match(evidence[1].args.description, /3 screenshots/);
});

test('run skips the gallery when the evidence delivery link upsert returns no id', async () => {
  let created = 0;
  const result = await run(evidenceContext({
    callTool: async (name) => (name === 'upsert-delivery-link'
      ? { isError: false, structured: {} }
      : { isError: false, structured: { id: 'check1' } }),
    createComment: async () => { created++; return { id: 0, html_url: 'u', body: '' }; },
  }));

  // Evidence ingestion throws on the missing id; the sync still completes and
  // simply posts no gallery rather than embedding broken image URLs.
  assert.equal(result.skipped, false);
  assert.equal(created, 0, 'no gallery comment is posted without a delivery link id');
});

test('run skips the gallery when an uploaded screenshot has no hosted URL', async () => {
  let created = 0;
  const result = await run(evidenceContext({
    callTool: async (name) => (name === 'upsert-delivery-link'
      ? { isError: false, structured: { id: 'link1' } }
      : { isError: false, structured: { id: 'check1' } }),
    // The upload drops one screenshot — its caption never comes back.
    uploadEvidence: async (deliveryLinkId, files) => files.slice(1).map((file) => ({
      caption: file.name,
      url: `https://growth.test/evidence-assets/${file.name}`,
    })),
    createComment: async () => { created++; return { id: 0, html_url: 'u', body: '' }; },
  }));

  assert.equal(result.skipped, false);
  assert.equal(created, 0, 'incomplete upload coverage posts no gallery rather than a broken one');
});

test('run posts the gallery uncited when a workflow run resolves no work item', async () => {
  const calls = [];
  let created = 0;
  let createdBody = '';
  const result = await run(evidenceContext({
    getCommitMessage: async () => 'No trailer on this commit',
    downloadArtifact: async () => evidenceZip(['dashboard/only.png']),
    uploadEvidence: async () => { throw new Error('should not upload without a work item'); },
    callTool: async (name, args) => {
      calls.push({ name, args });
      return { isError: false };
    },
    createComment: async (issueNumber, body) => {
      created++;
      createdBody = body;
      return { id: 100, html_url: 'u', body };
    },
    updateComment: async () => { throw new Error('should not update a comment'); },
  }));

  assert.equal(result.skipped, true);
  assert.equal(created, 1, 'the gallery is still posted');
  // With no delivery link to upload against, the gallery falls back to the
  // name manifest with the artifact download link.
  assert.match(createdBody, /Download the artifact/);
  assert.doesNotMatch(createdBody, /!\[/);
  assert.ok(
    !calls.some((c) => c.name === 'upsert-delivery-link' && c.args.type === 'evidence'),
    'an unresolved branch must not cite an evidence link',
  );
});

test('run updates the existing gallery comment in place rather than posting another', async () => {
  let created = 0;
  let updatedId = null;
  let updatedBody = null;
  const existingBody = buildGalleryComment({
    groups: [{ folder: 'old', files: ['old/stale.png'] }],
    artifactUrl: 'https://example.com/old',
  });
  await run(evidenceContext({
    callTool: async () => ({ isError: false, structured: { id: 'x' } }),
    listIssueComments: async () => [
      { id: 50, body: 'an unrelated comment' },
      { id: 51, body: existingBody },
    ],
    createComment: async () => { created++; return { id: 0, html_url: 'u', body: '' }; },
    updateComment: async (id, body) => { updatedId = id; updatedBody = body; return { id, html_url: 'u', body }; },
  }));

  assert.equal(created, 0, 'no second gallery comment is created');
  assert.equal(updatedId, 51, 'the marked comment is updated in place');
  assert.match(updatedBody, /!\[/, 'the updated comment embeds the screenshots inline');
});

test('run records the check run and skips the gallery when there is no evidence artifact', async () => {
  const calls = [];
  let touchedComments = false;
  const result = await run(evidenceContext({
    listRunArtifacts: async () => [{ id: 1, name: 'some-other-artifact', expired: false }],
    downloadArtifact: async () => { throw new Error('should not download'); },
    listIssueComments: async () => { touchedComments = true; return []; },
    createComment: async () => { touchedComments = true; return {}; },
    callTool: async (name, args) => {
      calls.push({ name, args });
      return name === 'upsert-delivery-link'
        ? { isError: false, structured: { id: 'link1' } }
        : { isError: false, structured: { id: 'check1' } };
    },
  }));

  assert.equal(result.skipped, false);
  assert.equal(touchedComments, false);
  assert.ok(calls.some((c) => c.name === 'upsert-check-run'), 'the check run is still recorded');
  assert.ok(!calls.some((c) => c.name === 'upsert-delivery-link' && c.args.type === 'evidence'));
});

test('run does not fail the sync when evidence ingestion throws', async () => {
  const calls = [];
  const warnings = [];
  const result = await run(evidenceContext({
    listRunArtifacts: async () => { throw new Error('artifacts API down'); },
    callTool: async (name, args) => {
      calls.push({ name, args });
      return name === 'upsert-delivery-link'
        ? { isError: false, structured: { id: 'link1' } }
        : { isError: false, structured: { id: 'check1' } };
    },
    log: { ...silentLog(), warn: (m) => warnings.push(m) },
  }));

  assert.equal(result.skipped, false);
  assert.ok(calls.some((c) => c.name === 'upsert-check-run'), 'the check run is still recorded');
  assert.ok(warnings.some((m) => /ingest visual evidence/i.test(m)), 'the failure is warned, not thrown');
});
