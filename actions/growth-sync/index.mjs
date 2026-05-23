// Growth sync runner. Translates GitHub pull_request events into MCP
// calls against a Growth instance. Pure helpers and the orchestration
// `run` function are exported for testing; the bottom of the file is the
// GitHub Action entrypoint.

import { inflateRawSync } from 'node:zlib';
import { basename } from 'node:path';

const TRAILER_KEY = 'Growth-Work-Item';
const CHANGE_REQUEST_TRAILER_KEY = 'Growth-Change-Request';

// Name of the GitHub check growth-sync posts back to a pull request to
// make a missed work item attribution loud instead of silent.
const ATTRIBUTION_CHECK_NAME = 'Growth: work item attribution';

// Fixed name of the build artifact a project uploads its `docs/evidence/`
// screenshots under. growth-sync ingests the artifact with this exact name;
// the contract is documented in docs/evidence/README.md.
const EVIDENCE_ARTIFACT_NAME = 'growth-evidence';

// Hidden marker on the screenshot-gallery comment. growth-sync finds its own
// comment by this marker so each push updates the one comment in place rather
// than posting a fresh one.
const GALLERY_MARKER = '<!-- growth-sync:evidence-gallery -->';

/**
 * Extract the work item id from a commit message's `Growth-Work-Item:`
 * trailer. The last matching trailer wins, per git trailer convention.
 * Returns null when no trailer is present.
 */
export function parseTrailer(commitMessage) {
  return parseNamedTrailer(commitMessage, TRAILER_KEY);
}

export function parseChangeRequestTrailer(commitMessage) {
  return parseNamedTrailer(commitMessage, CHANGE_REQUEST_TRAILER_KEY);
}

function parseNamedTrailer(commitMessage, trailerKey) {
  const pattern = new RegExp(`^${trailerKey}:\\s*(\\S+)\\s*$`, 'gim');
  let workItemId = null;
  let match;
  while ((match = pattern.exec(commitMessage ?? '')) !== null) {
    workItemId = match[1];
  }
  return workItemId;
}

/**
 * Extract a per-project work item reference (`WI-<number>`) from a branch
 * name, case-insensitively. The reference may sit anywhere in the branch
 * (`WI-42-add-login`, `feature/wi-42`) as long as it is delimited by a slash,
 * dash, underscore, or the branch boundary. Returns the canonical
 * `WI-<number>` form, or null when the branch carries no reference.
 */
export function parseBranchReference(branch) {
  const match = /(?:^|[/_-])WI-(\d+)(?=$|[/_-])/i.exec(branch ?? '');
  return match ? `WI-${Number(match[1])}` : null;
}

export function parseChangeRequestBranchReference(branch) {
  const match = /(?:^|[/_-])CR-(\d+)(?=$|[/_-])/i.exec(branch ?? '');
  return match ? `CR-${Number(match[1])}` : null;
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
 * Per-entry uncompressed-size ceiling for evidence ZIP extraction. Full-page
 * PNG screenshots run a few MiB; 64 MiB is generous headroom while still
 * bounding a zip bomb's blast radius on the action runner.
 */
const MAX_EVIDENCE_ENTRY_BYTES = 64 * 1024 * 1024;

/**
 * Extract every file from a ZIP buffer as `{ name, bytes }`. The central
 * directory carries each entry's name, compression method, compressed size,
 * and the offset of its local header; the local header is consulted only for
 * its own name/extra lengths, to find where the entry's data begins. Sizes
 * are taken from the central directory because an entry written with a data
 * descriptor (general-purpose bit 3) leaves the local header's size fields
 * zero. Stored (method 0) and DEFLATE (method 8) entries are supported.
 * Directory entries (names ending in `/`) are dropped. Throws on a buffer
 * that is not a ZIP archive, or on an entry whose uncompressed size exceeds
 * the evidence limit — the artifact is effectively untrusted input, so a
 * highly-compressed "zip bomb" must not be allowed to exhaust the runner.
 */
export function extractZipFiles(buffer) {
  const EOCD_SIGNATURE = 0x06054b50;
  const CENTRAL_DIRECTORY_SIGNATURE = 0x02014b50;
  const LOCAL_HEADER_SIGNATURE = 0x04034b50;

  // The end-of-central-directory record is 22 bytes plus an optional trailing
  // comment (max 65535). Scan back from the end for its signature.
  let eocd = -1;
  const earliest = Math.max(0, buffer.length - 22 - 0xffff);
  for (let i = buffer.length - 22; i >= earliest; i--) {
    if (buffer.readUInt32LE(i) === EOCD_SIGNATURE) {
      eocd = i;
      break;
    }
  }
  if (eocd === -1) {
    throw new Error('Not a ZIP archive: no end-of-central-directory record.');
  }

  const entryCount = buffer.readUInt16LE(eocd + 10);
  let offset = buffer.readUInt32LE(eocd + 16);
  const files = [];
  for (let i = 0; i < entryCount; i++) {
    if (buffer.readUInt32LE(offset) !== CENTRAL_DIRECTORY_SIGNATURE) {
      throw new Error('Corrupt ZIP archive: bad central-directory entry.');
    }
    const compression = buffer.readUInt16LE(offset + 10);
    const compressedSize = buffer.readUInt32LE(offset + 20);
    const uncompressedSize = buffer.readUInt32LE(offset + 24);
    const nameLength = buffer.readUInt16LE(offset + 28);
    const extraLength = buffer.readUInt16LE(offset + 30);
    const commentLength = buffer.readUInt16LE(offset + 32);
    const localOffset = buffer.readUInt32LE(offset + 42);
    const name = buffer.toString('utf8', offset + 46, offset + 46 + nameLength);

    if (!name.endsWith('/')) {
      if (buffer.readUInt32LE(localOffset) !== LOCAL_HEADER_SIGNATURE) {
        throw new Error('Corrupt ZIP archive: bad local file header.');
      }
      // The local header's own name/extra lengths can differ from the
      // central-directory entry's, so re-read them to find the data offset.
      const localNameLength = buffer.readUInt16LE(localOffset + 26);
      const localExtraLength = buffer.readUInt16LE(localOffset + 28);
      const dataStart = localOffset + 30 + localNameLength + localExtraLength;
      const compressed = buffer.subarray(dataStart, dataStart + compressedSize);

      // The central directory's uncompressed size is trustworthy even for
      // data-descriptor entries; reject an oversized entry before inflating.
      if (uncompressedSize > MAX_EVIDENCE_ENTRY_BYTES) {
        throw new Error(
          `ZIP entry "${name}" declares ${uncompressedSize} uncompressed bytes, `
          + `exceeding the ${MAX_EVIDENCE_ENTRY_BYTES}-byte evidence limit.`,
        );
      }

      let bytes;
      if (compression === 0) {
        bytes = Buffer.from(compressed);
      } else if (compression === 8) {
        // maxOutputLength caps the actual inflated size too, in case the
        // declared uncompressed size understates a crafted entry.
        bytes = inflateRawSync(compressed, { maxOutputLength: MAX_EVIDENCE_ENTRY_BYTES });
      } else {
        throw new Error(`Unsupported ZIP compression method ${compression} for "${name}".`);
      }
      files.push({ name, bytes });
    }
    offset += 46 + nameLength + extraLength + commentLength;
  }
  return files;
}

/**
 * Group evidence screenshot paths by their top-level folder. The artifact
 * root is `docs/evidence/`, so a path is `<slug>/<screenshot>.png`. Non-PNG
 * files and files sitting at the artifact root (no folder) are dropped.
 * Returns folders sorted by name, each with its files sorted — a stable
 * gallery across runs.
 */
export function groupEvidenceFiles(paths) {
  const groups = new Map();
  for (const path of paths ?? []) {
    if (!path.toLowerCase().endsWith('.png')) {
      continue;
    }
    const slash = path.indexOf('/');
    if (slash <= 0) {
      continue;
    }
    const folder = path.slice(0, slash);
    if (!groups.has(folder)) {
      groups.set(folder, []);
    }
    groups.get(folder).push(path);
  }
  return [...groups.entries()]
    .map(([folder, files]) => ({ folder, files: files.sort() }))
    .sort((a, b) => a.folder.localeCompare(b.folder));
}

/**
 * Render an artifact-supplied name (folder or file) inside a Markdown inline
 * code span. The name comes from ZIP entries, so it is untrusted: control
 * characters are stripped and backticks neutralised so a crafted filename
 * cannot break the span or inject headings, links, or `@mentions`.
 */
function codeSpan(name) {
  return `\`${String(name).replace(/[\u0000-\u001f\u007f]/g, '').replace(/`/g, "'")}\``;
}

/**
 * Sanitise a screenshot path for use as Markdown image alt text. The path
 * comes from a ZIP entry, so it is untrusted: control characters are
 * stripped and square brackets removed so a crafted filename cannot break
 * out of the `![ ... ]` span.
 */
function altText(name) {
  return String(name).replace(/[\u0000-\u001f\u007f]/g, '').replace(/[[\]]/g, '');
}

/**
 * Build the screenshot-gallery comment body: the hidden marker, a header, and
 * the captured screenshots grouped by folder.
 *
 * With `assets` — a Map of screenshot path to its Growth-hosted public URL —
 * each screenshot is embedded inline as a Markdown image. Without it (no work
 * item resolved, so nothing was uploaded) the gallery falls back to a
 * manifest of names with a link to the run artifact.
 */
export function buildGalleryComment({ groups, assets, artifactUrl }) {
  const total = groups.reduce((sum, group) => sum + group.files.length, 0);
  const inline = assets != null;
  const lines = [
    GALLERY_MARKER,
    '## 📸 Visual evidence',
    '',
    inline
      ? `${total} screenshot${total === 1 ? '' : 's'} captured by CI across `
        + `${groups.length} folder${groups.length === 1 ? '' : 's'}.`
      : `${total} screenshot${total === 1 ? '' : 's'} captured by CI across `
        + `${groups.length} folder${groups.length === 1 ? '' : 's'}. `
        + `[Download the artifact](${artifactUrl}) — GitHub expires run artifacts, `
        + 'so download it to keep the screenshots.',
    '',
  ];
  for (const group of groups) {
    lines.push(`### ${codeSpan(group.folder)}`);
    for (const file of group.files) {
      if (inline) {
        lines.push(`![${altText(file)}](${assets.get(file)})`);
        lines.push('');
      } else {
        lines.push(`- ${codeSpan(file)}`);
      }
    }
    if (!inline) {
      lines.push('');
    }
  }
  return `${lines.join('\n').trimEnd()}\n`;
}

/**
 * Find growth-sync's own screenshot-gallery comment among a pull request's
 * comments by its hidden marker. Returns the comment, or null when none has
 * been posted yet.
 */
export function findGalleryComment(comments) {
  return (comments ?? []).find((comment) => (comment.body ?? '').includes(GALLERY_MARKER)) ?? null;
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

async function resolveChangeRequestByBranch({ callTool, repository, branch, log }) {
  if (!branch) {
    return { changeRequestId: null, ambiguous: false };
  }
  const result = await callTool('resolve-change-request-by-branch', {
    github_repo: repository,
    branch,
  });
  if (result.isError) {
    throw new Error(`Growth rejected the change-request branch lookup: ${result.errorText}`);
  }
  const ambiguous = result.structured?.ambiguous === true;
  if (ambiguous) {
    log?.warn(`Branch ${branch} is bound to more than one change request; cannot attribute trailer-less commits.`);
  }
  return {
    changeRequestId: result.structured?.found ? result.structured.change_request_id : null,
    ambiguous,
  };
}

/**
 * Resolve a per-project work item reference (`WI-<number>`) found in a branch
 * name to a work item id within the repository. Returns the work item id, or
 * null when no work item carries that reference.
 */
async function resolveWorkItemByReference({ callTool, repository, reference }) {
  const result = await callTool('resolve-work-item-by-reference', {
    github_repo: repository,
    reference,
  });
  if (result.isError) {
    throw new Error(`Growth rejected the reference lookup: ${result.errorText}`);
  }
  return result.structured?.found ? result.structured.work_item_id : null;
}

async function resolveChangeRequestByReference({ callTool, repository, reference }) {
  const result = await callTool('resolve-change-request-by-reference', {
    github_repo: repository,
    reference,
  });
  if (result.isError) {
    throw new Error(`Growth rejected the change-request reference lookup: ${result.errorText}`);
  }
  return result.structured?.found ? result.structured.change_request_id : null;
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

async function recordChangeRequestBranchBinding({ callTool, repository, changeRequestId, branch }) {
  const branchPath = branch.split('/').map(encodeURIComponent).join('/');
  const result = await callTool('upsert-change-request-delivery-link', {
    change_request_id: changeRequestId,
    type: 'branch',
    ref: branch,
    url: `https://github.com/${repository}/tree/${branchPath}`,
  });
  if (result.isError) {
    throw new Error(`Growth rejected the change-request branch link: ${result.errorText}`);
  }
}

function changeRequestTrailerIsReference(value) {
  return /^(?:CR-)?0*\d+$/i.test(String(value ?? '').trim());
}

/**
 * Resolve a Growth delivery target from commit trailers, branch references,
 * and finally branch delivery-link bindings. Work item attribution stays
 * first for compatibility; change requests cover CR-only work that has no
 * work item. Returns `{ kind, id }`, or null when unattributable.
 */
async function attributeDeliveryTarget({ callTool, repository, eventName, commitMessage, branch, sha, url, isFork = false, log }) {
  const trailerId = parseTrailer(commitMessage);
  if (trailerId !== null) {
    // Two forks can share a head ref, so a fork branch must never become a
    // base-repo branch binding. The PR-number link still carries attribution
    // for later trailer-less events on the same pull request.
    if (branch && !isFork) {
      await recordBranchBinding({ callTool, repository, workItemId: trailerId, branch });
    }
    return { kind: 'work_item', id: trailerId };
  }

  const changeRequestTrailer = parseChangeRequestTrailer(commitMessage);
  if (changeRequestTrailer !== null) {
    const changeRequestId = changeRequestTrailerIsReference(changeRequestTrailer)
      ? await resolveChangeRequestByReference({ callTool, repository, reference: changeRequestTrailer })
      : changeRequestTrailer;
    if (changeRequestId === null) {
      throw new Error(`Growth could not resolve change request trailer ${changeRequestTrailer}.`);
    }
    if (branch && !isFork) {
      await recordChangeRequestBranchBinding({ callTool, repository, changeRequestId, branch });
    }
    return { kind: 'change_request', id: changeRequestId };
  }

  // A `WI-NNN` token in the branch name is a per-project work item reference,
  // not a branch binding: it names one work item directly, so — unlike a
  // branch delivery link — it carries no fork collision risk and is resolved
  // even for fork pull requests. It is checked before the branch binding so
  // an explicit reference always wins over a (possibly stale) bound branch.
  const reference = parseBranchReference(branch);
  if (reference !== null) {
    const referencedId = await resolveWorkItemByReference({ callTool, repository, reference });
    if (referencedId !== null) {
      log.info(`Attributed commit ${sha} to work item ${referencedId} via branch reference ${reference}.`);
      return { kind: 'work_item', id: referencedId };
    }
    log.warn(`Branch ${branch} carries reference ${reference}, but no work item matches it in ${repository}.`);
  }

  const changeRequestReference = parseChangeRequestBranchReference(branch);
  if (changeRequestReference !== null) {
    const referencedId = await resolveChangeRequestByReference({
      callTool,
      repository,
      reference: changeRequestReference,
    });
    if (referencedId !== null) {
      log.info(`Attributed commit ${sha} to change request ${referencedId} via branch reference ${changeRequestReference}.`);
      return { kind: 'change_request', id: referencedId };
    }
    log.warn(`Branch ${branch} carries reference ${changeRequestReference}, but no change request matches it in ${repository}.`);
  }

  // A fork's trailer-less commit cannot borrow a base-repo branch binding:
  // another fork may have created it for an unrelated same-named branch.
  const { workItemId, ambiguous } = isFork
    ? { workItemId: null, ambiguous: false }
    : await resolveWorkItemByBranch({ callTool, repository, branch, log });
  if (workItemId !== null) {
    log.info(`Attributed commit ${sha} to work item ${workItemId} via branch ${branch}.`);
    return { kind: 'work_item', id: workItemId };
  }
  if (ambiguous) {
    log.warn(`No ${TRAILER_KEY} trailer on commit ${sha} and branch ${branch ?? '(unknown)'} is ambiguous; skipping.`);
    await recordUnattributedEvent({
      callTool,
      repository,
      eventName,
      branch,
      sha,
      reason: 'ambiguous_branch',
      url,
      log,
    });
    return null;
  }

  const { changeRequestId, ambiguous: crAmbiguous } = isFork
    ? { changeRequestId: null, ambiguous: false }
    : await resolveChangeRequestByBranch({ callTool, repository, branch, log });
  if (changeRequestId !== null) {
    log.info(`Attributed commit ${sha} to change request ${changeRequestId} via branch ${branch}.`);
    return { kind: 'change_request', id: changeRequestId };
  }

  log.warn(`No Growth attribution trailer on commit ${sha} and no work item or change request bound to branch ${branch ?? '(unknown)'}; skipping.`);
  await recordUnattributedEvent({
    callTool,
    repository,
    eventName,
    branch,
    sha,
    reason: crAmbiguous ? 'ambiguous_branch' : 'missing_link',
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

export function buildChangeRequestDeliveryLinkArgs(event, changeRequestId) {
  const pr = event.pull_request ?? {};
  return {
    change_request_id: changeRequestId,
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
 * Build the GitHub check-run payload for the attribution check.
 * A resolved work item or change request passes; an unresolved one fails
 * (blocking) or is flagged neutral (advisory), with output naming the ways
 * to fix it.
 */
export function buildAttributionCheckArgs({ headSha, target, workItemId, branch, advisory }) {
  const normalizedTarget = target ?? (workItemId != null ? { kind: 'work_item', id: workItemId } : null);
  const resolved = normalizedTarget !== null;
  const targetLabel = normalizedTarget?.kind === 'change_request' ? 'change request' : 'work item';
  const output = resolved
    ? {
        title: `Attributed to ${targetLabel} ${normalizedTarget.id}`,
        summary: `This pull request's commits are attributed to Growth ${targetLabel} ${normalizedTarget.id}.`,
      }
    : {
        title: 'No Growth work item or change request resolved',
        summary: [
          'growth-sync could not attribute this pull request to a Growth work item or change request, ',
          'so its delivery evidence will not be recorded.',
          '\n\nFix it one of three ways:\n',
          '- Name the branch with a `WI-<number>` or `CR-<number>` reference, e.g. `WI-42-short-description`, or\n',
          `- Add a \`${TRAILER_KEY}: <id>\` trailer to a commit on \`${branch ?? 'this branch'}\`, or\n`,
          `- Add a \`${CHANGE_REQUEST_TRAILER_KEY}: <id-or-CR-number>\` trailer, or\n`,
          '- Bind the branch with `upsert-delivery-link` or `upsert-change-request-delivery-link` (type `branch`).',
          '\n\nRe-run this workflow once the reference or link exists and the check will pass.',
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
async function reportAttribution({ event, repository, target, branch, postCheckRun, advisory, log }) {
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

  const args = buildAttributionCheckArgs({ headSha, target, branch, advisory });
  try {
    await postCheckRun(args);
    log.info(`Posted "${ATTRIBUTION_CHECK_NAME}" check (${args.conclusion}) on ${headSha}.`);
  } catch (error) {
    if (error.status === 403) {
      // A 403 on the check-runs POST is almost always a misconfigured
      // workflow missing `permissions: checks: write`. A swallowed warning
      // is exactly how that drift goes unnoticed, so make it loud — the
      // sync itself still succeeded, this only reports the gap.
      log.error(
        'growth-sync could not post the work item attribution check (HTTP 403). '
        + 'Most likely cause: this workflow is missing `permissions: checks: write`. '
        + 'Add to .github/workflows/growth-sync.yml:\n'
        + '  permissions:\n'
        + '    contents: read\n'
        + '    checks: write\n'
        + 'If that block is already present, check the repository or '
        + 'organization Actions workflow-token permissions.'
      );
    } else {
      log.warn(`Could not post the attribution check: ${error.message}`);
    }
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
  const target = await attributeDeliveryTarget({
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
    target,
    branch,
    postCheckRun,
    advisory: attributionCheckAdvisory,
    log,
  });

  if (target === null) {
    return { skipped: true };
  }

  const args = target.kind === 'work_item'
    ? buildDeliveryLinkArgs(event, target.id)
    : buildChangeRequestDeliveryLinkArgs(event, target.id);
  const result = await callTool(
    target.kind === 'work_item' ? 'upsert-delivery-link' : 'upsert-change-request-delivery-link',
    args,
  );
  if (result.isError) {
    throw new Error(`Growth rejected the delivery link: ${result.errorText}`);
  }

  log.info(`Recorded delivery link ${args.ref} on ${target.kind === 'work_item' ? 'work item' : 'change request'} ${target.id}.`);
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
  const target = await attributeDeliveryTarget({
    callTool,
    repository,
    eventName: 'check_run',
    commitMessage,
    branch,
    sha,
    url: event.check_run?.html_url,
    log,
  });
  if (target === null) {
    return { skipped: true };
  }
  if (target.kind === 'change_request') {
    log.info(`Check run resolved only to change request ${target.id}; check evidence is work-item scoped, so no check-run evidence was recorded.`);
    return { skipped: true };
  }

  const linkArgs = {
    work_item_id: target.id,
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
 * Ingest a CI run's visual-evidence artifact: upload its screenshots to
 * Growth, post — or update in place — the per-pull-request gallery comment
 * with the images embedded inline, and cite the gallery on the matched work
 * item as an `evidence` delivery link.
 *
 * The evidence delivery link is recorded first because the upload is scoped
 * to it; it is then re-upserted with the posted comment's URL. A run whose
 * branch resolves to no work item has no delivery link to upload against, so
 * its gallery falls back to the name-manifest rendering and is posted uncited.
 * A run with no evidence artifact is skipped. None of these are failures —
 * the caller treats a throw as best-effort so a flaky artifact API never
 * fails the whole sync.
 */
async function ingestEvidence({
  runId,
  repository,
  pullRequest,
  workItemId,
  listRunArtifacts,
  downloadArtifact,
  uploadEvidence,
  listIssueComments,
  createComment,
  updateComment,
  callTool,
  log,
}) {
  if (typeof listRunArtifacts !== 'function') {
    return; // Evidence ingestion is not wired into this caller.
  }

  const artifacts = await listRunArtifacts(runId);
  const artifact = (artifacts ?? []).find(
    (candidate) => candidate.name === EVIDENCE_ARTIFACT_NAME && !candidate.expired,
  );
  if (!artifact) {
    log.info(`No ${EVIDENCE_ARTIFACT_NAME} artifact on run ${runId}; nothing to ingest.`);
    return;
  }
  if (!pullRequest) {
    log.info('Workflow run has no pull request; cannot post an evidence gallery.');
    return;
  }

  const files = extractZipFiles(await downloadArtifact(artifact.id));
  const groups = groupEvidenceFiles(files.map((file) => file.name));
  if (groups.length === 0) {
    log.warn(`The ${EVIDENCE_ARTIFACT_NAME} artifact held no screenshots; skipping the gallery.`);
    return;
  }

  const total = groups.reduce((sum, group) => sum + group.files.length, 0);
  const artifactUrl = `https://github.com/${repository}/actions/runs/${runId}/artifacts/${artifact.id}`;
  const hasWorkItem = workItemId !== null && workItemId !== undefined;

  // The evidence delivery link must exist before the upload, which is scoped
  // to it. With no work item there is nothing to scope an upload to, so the
  // gallery falls back to the name manifest.
  let assets = null;
  if (hasWorkItem) {
    const linkResult = await callTool('upsert-delivery-link', {
      work_item_id: workItemId,
      type: 'evidence',
      ref: `#${pullRequest.number}`,
    });
    if (linkResult.isError) {
      throw new Error(`Growth rejected the evidence link: ${linkResult.errorText}`);
    }
    const deliveryLinkId = linkResult.structured?.id;
    if (!deliveryLinkId) {
      throw new Error('Growth accepted the evidence link but returned no id; cannot scope the upload.');
    }

    // The gallery's paths, in folder/name order, paired back to their bytes
    // via a single name→file index — constant-time lookup per path.
    const filesByName = new Map(files.map((file) => [file.name, file]));
    const galleryFiles = groups.flatMap((group) => group.files).map((path) => {
      const file = filesByName.get(path);
      if (!file) {
        throw new Error(`Evidence artifact is missing screenshot bytes for "${path}".`);
      }
      return file;
    });
    const uploaded = await uploadEvidence(deliveryLinkId, galleryFiles);
    assets = new Map(uploaded.map((asset) => [asset.caption, asset.url]));

    // Every gallery path must have come back with a hosted URL, or the inline
    // comment would render `![alt](undefined)`. Fail loudly instead.
    for (const file of galleryFiles) {
      if (!assets.has(file.name)) {
        throw new Error(`Growth returned no hosted URL for screenshot "${file.name}".`);
      }
    }
  }

  const body = buildGalleryComment(assets ? { groups, assets } : { groups, artifactUrl });
  const existing = findGalleryComment(await listIssueComments(pullRequest.number));
  const comment = existing
    ? await updateComment(existing.id, body)
    : await createComment(pullRequest.number, body);

  log.info(
    `${existing ? 'Updated' : 'Posted'} the evidence gallery (${total} screenshot(s)) `
    + `on pull request #${pullRequest.number}.`,
  );

  if (!hasWorkItem) {
    log.info('No work item resolved for this run; the gallery is posted but not cited.');
    return;
  }

  // Re-upsert the same evidence link — keyed on (work item, type, ref) — now
  // that the gallery comment exists, to record its URL and screenshot count.
  const result = await callTool('upsert-delivery-link', {
    work_item_id: workItemId,
    type: 'evidence',
    ref: `#${pullRequest.number}`,
    url: comment.html_url,
    description: `Visual evidence: ${total} screenshot${total === 1 ? '' : 's'}`,
  });
  if (result.isError) {
    throw new Error(`Growth rejected the evidence link: ${result.errorText}`);
  }
  log.info(`Cited the evidence gallery on work item ${workItemId}.`);
}

/**
 * Orchestrate one workflow_run event: resolve the PR's delivery link
 * (creating it if the PR event has not been seen yet) then record the
 * run as check evidence. This is the GitHub Actions counterpart to
 * runCheckRun, which only fires for third-party CI providers.
 */
async function runWorkflowRun({
  event,
  repository,
  getCommitMessage,
  callTool,
  listRunArtifacts,
  downloadArtifact,
  uploadEvidence,
  listIssueComments,
  createComment,
  updateComment,
  log,
}) {
  const pullRequest = resolveWorkflowRunPullRequest(event);
  if (pullRequest === null) {
    log.warn('Workflow run has no associated pull request; skipping.');
    return { skipped: true };
  }

  const run = event.workflow_run ?? {};
  const branch = resolveEventBranch('workflow_run', event);
  const commitMessage = await getCommitMessage(run.head_sha, run.head_repository?.full_name);
  const target = await attributeDeliveryTarget({
    callTool,
    repository,
    eventName: 'workflow_run',
    commitMessage,
    branch,
    sha: run.head_sha,
    url: run.html_url,
    log,
  });

  // Ingest the run's visual-evidence artifact, if any. Runs whether or not a
  // work item resolved — an unresolved branch still gets the gallery posted,
  // uncited. Best-effort: a flaky artifact or comment API must not fail the
  // delivery-link and check-run sync below.
  try {
    await ingestEvidence({
      runId: run.id,
      repository,
      pullRequest,
      workItemId: target?.kind === 'work_item' ? target.id : null,
      listRunArtifacts,
      downloadArtifact,
      uploadEvidence,
      listIssueComments,
      createComment,
      updateComment,
      callTool,
      log,
    });
  } catch (error) {
    log.warn(`Could not ingest visual evidence: ${error.message}`);
  }

  if (target === null) {
    return { skipped: true };
  }
  if (target.kind === 'change_request') {
    log.info(`Workflow run resolved only to change request ${target.id}; check evidence is work-item scoped, so no check-run evidence was recorded.`);
    return { skipped: true };
  }

  const linkArgs = {
    work_item_id: target.id,
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
 * surface the Growth attribution result back onto the pull request.
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
      // Carry the HTTP status on the error so the caller can branch on it
      // (a 403 means missing `checks: write`) without parsing the message.
      const error = new Error(`GitHub API ${response.status} posting check run`);
      error.status = response.status;
      throw error;
    }
    return response.json();
  };
}

/**
 * List the artifacts a workflow run produced. growth-sync looks among them
 * for the visual-evidence artifact to ingest.
 */
function makeArtifactLister({ apiUrl, repository, token, fetchFn }) {
  return async function listRunArtifacts(runId) {
    const response = await fetchFn(
      `${apiUrl}/repos/${repository}/actions/runs/${runId}/artifacts?per_page=100`,
      {
        headers: {
          Authorization: `Bearer ${token}`,
          Accept: 'application/vnd.github+json',
          'User-Agent': 'growth-sync',
        },
      },
    );
    if (!response.ok) {
      throw new Error(`GitHub API ${response.status} listing artifacts for run ${runId}`);
    }
    const body = await response.json();
    return body.artifacts ?? [];
  };
}

/**
 * Download a run artifact's zip into a Buffer. The endpoint 302-redirects to
 * a short-lived signed blob URL; fetch follows the redirect.
 */
function makeArtifactDownloader({ apiUrl, repository, token, fetchFn }) {
  return async function downloadArtifact(artifactId) {
    const response = await fetchFn(
      `${apiUrl}/repos/${repository}/actions/artifacts/${artifactId}/zip`,
      {
        headers: {
          Authorization: `Bearer ${token}`,
          Accept: 'application/vnd.github+json',
          'User-Agent': 'growth-sync',
        },
      },
    );
    if (!response.ok) {
      throw new Error(`GitHub API ${response.status} downloading artifact ${artifactId}`);
    }
    return Buffer.from(await response.arrayBuffer());
  };
}

/**
 * List, create, and update issue comments — a pull request's comments are
 * issue comments — so growth-sync can keep a single screenshot-gallery
 * comment per pull request, updated in place.
 */
function makeCommentClient({ apiUrl, repository, token, fetchFn }) {
  const headers = {
    Authorization: `Bearer ${token}`,
    Accept: 'application/vnd.github+json',
    'Content-Type': 'application/json',
    'User-Agent': 'growth-sync',
  };
  return {
    listIssueComments: async (issueNumber) => {
      // Page through every comment: a long-lived pull request can carry more
      // than one page, and missing the gallery marker would post a duplicate.
      const comments = [];
      for (let page = 1; ; page++) {
        const response = await fetchFn(
          `${apiUrl}/repos/${repository}/issues/${issueNumber}/comments?per_page=100&page=${page}`,
          { headers },
        );
        if (!response.ok) {
          throw new Error(`GitHub API ${response.status} listing comments on #${issueNumber}`);
        }
        const batch = await response.json();
        comments.push(...batch);
        if (batch.length < 100) {
          return comments;
        }
      }
    },
    createComment: async (issueNumber, body) => {
      const response = await fetchFn(
        `${apiUrl}/repos/${repository}/issues/${issueNumber}/comments`,
        { method: 'POST', headers, body: JSON.stringify({ body }) },
      );
      if (!response.ok) {
        throw new Error(`GitHub API ${response.status} creating a comment on #${issueNumber}`);
      }
      return response.json();
    },
    updateComment: async (commentId, body) => {
      const response = await fetchFn(
        `${apiUrl}/repos/${repository}/issues/comments/${commentId}`,
        { method: 'PATCH', headers, body: JSON.stringify({ body }) },
      );
      if (!response.ok) {
        throw new Error(`GitHub API ${response.status} updating comment ${commentId}`);
      }
      return response.json();
    },
  };
}

/**
 * Upload a pull request's visual-evidence screenshots to Growth in a single
 * multipart request. The endpoint replaces the delivery link's whole gallery
 * on every call, so all of a run's screenshots must go in one POST. Returns
 * the stored assets, each carrying a stable public URL to embed.
 */
function makeEvidenceUploader({ growthUrl, token, fetchFn }) {
  return async function uploadEvidence(deliveryLinkId, files) {
    const form = new FormData();
    form.append('delivery_link_id', deliveryLinkId);
    for (const file of files) {
      // The caption is the screenshot's full artifact path so the gallery can
      // group the uploaded assets back by folder; the multipart filename is
      // just its base name.
      form.append('images[]', new Blob([file.bytes], { type: 'image/png' }), basename(file.name));
      form.append('captions[]', file.name);
    }
    const response = await fetchFn(`${growthUrl.replace(/\/$/, '')}/api/evidence-assets`, {
      method: 'POST',
      headers: {
        Authorization: `Bearer ${token}`,
        Accept: 'application/json',
      },
      body: form,
    });
    if (!response.ok) {
      throw new Error(`Growth evidence upload HTTP ${response.status}`);
    }
    const body = await response.json();
    return body.assets ?? [];
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
  const listRunArtifacts = makeArtifactLister({
    apiUrl,
    repository: GITHUB_REPOSITORY,
    token: githubToken,
    fetchFn: fetch,
  });
  const downloadArtifact = makeArtifactDownloader({
    apiUrl,
    repository: GITHUB_REPOSITORY,
    token: githubToken,
    fetchFn: fetch,
  });
  const { listIssueComments, createComment, updateComment } = makeCommentClient({
    apiUrl,
    repository: GITHUB_REPOSITORY,
    token: githubToken,
    fetchFn: fetch,
  });
  const callTool = makeMcpClient({ growthUrl, token: growthToken, fetchFn: fetch });
  const uploadEvidence = makeEvidenceUploader({ growthUrl, token: growthToken, fetchFn: fetch });

  await run({
    eventName: GITHUB_EVENT_NAME,
    event,
    repository: GITHUB_REPOSITORY,
    getCommitMessage,
    postCheckRun,
    listRunArtifacts,
    downloadArtifact,
    uploadEvidence,
    listIssueComments,
    createComment,
    updateComment,
    attributionCheckAdvisory: process.env.GROWTH_ATTRIBUTION_CHECK === 'advisory',
    callTool,
    log: {
      info: (m) => console.log(m),
      warn: (m) => console.warn(m),
      // `::error::` renders as a loud annotation on the GitHub run summary.
      // Workflow commands are single-line, so the message data is encoded.
      error: (m) => console.log(`::error title=growth-sync::${encodeAnnotation(m)}`),
    },
  });
}

/**
 * Encode a message for a GitHub Actions workflow command. The command is a
 * single line, so literal `%`, carriage returns, and newlines are escaped.
 */
export function encodeAnnotation(message) {
  return message.replace(/%/g, '%25').replace(/\r/g, '%0D').replace(/\n/g, '%0A');
}

if (process.argv[1] && import.meta.url === `file://${process.argv[1]}`) {
  main().catch((error) => {
    console.error(error.message);
    process.exit(1);
  });
}
