import assert from 'node:assert/strict';
import { test } from 'node:test';

import {
  buildAttributionCheckArgs,
  buildCheckRunArgs,
  buildDeliveryLinkArgs,
  buildDeploymentArgs,
  buildReleaseArgs,
  buildWorkflowRunArgs,
  encodeAnnotation,
  isForkPullRequest,
  mapDeploymentState,
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
