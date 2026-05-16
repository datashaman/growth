import assert from 'node:assert/strict';
import { test } from 'node:test';

import {
  buildCheckRunArgs,
  buildDeliveryLinkArgs,
  buildDeploymentArgs,
  buildReleaseArgs,
  mapDeploymentState,
  parseTrailer,
  resolveCheckRunPullRequest,
  resolveCommitSha,
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
  return { info: () => {}, warn: () => {} };
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

test('run skips and does not call Growth when no trailer is present', async () => {
  let called = false;
  const result = await run({
    eventName: 'pull_request',
    event: { action: 'opened', pull_request: { number: 1, head: { sha: 's' } } },
    getCommitMessage: async () => 'No trailer here',
    callTool: async () => {
      called = true;
      return { isError: false };
    },
    log: silentLog(),
  });

  assert.equal(result.skipped, true);
  assert.equal(called, false);
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

test('run skips when Growth rejects the delivery link', async () => {
  const result = await run({
    eventName: 'pull_request',
    event: {
      action: 'synchronize',
      pull_request: { number: 9, html_url: 'u', title: 't', head: { sha: 's' } },
    },
    getCommitMessage: async () => 'Growth-Work-Item: 01MISSING',
    callTool: async () => ({ isError: true, errorText: 'work item not found' }),
    log: silentLog(),
  });

  assert.equal(result.skipped, true);
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

test('run skips a check run whose commit has no trailer', async () => {
  let called = false;
  const result = await run({
    eventName: 'check_run',
    event: checkRunEvent(),
    repository: 'datashaman/growth',
    getCommitMessage: async () => 'No trailer',
    callTool: async () => {
      called = true;
      return { isError: false };
    },
    log: silentLog(),
  });

  assert.equal(result.skipped, true);
  assert.equal(called, false);
});

test('run skips when Growth rejects the check run', async () => {
  const result = await run({
    eventName: 'check_run',
    event: checkRunEvent(),
    repository: 'datashaman/growth',
    getCommitMessage: async () => 'Growth-Work-Item: 01WI',
    callTool: async (name) =>
      name === 'upsert-delivery-link'
        ? { isError: false, structured: { id: 'link1' } }
        : { isError: true, errorText: 'bad conclusion' },
    log: silentLog(),
  });

  assert.equal(result.skipped, true);
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
