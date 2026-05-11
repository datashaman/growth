<?php

namespace App\Growth\Ingest;

use App\Models\Project;
use App\Models\ProjectPlan;
use App\Models\Requirement;
use App\Models\Source;
use App\Models\WorkItem;
use App\Models\WorkItemDeliveryLink;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Process\Process;

class LocalProjectIngestor
{
    /**
     * @return array<string, mixed>
     */
    public function ingest(string $path, array $options = []): array
    {
        $repoPath = $this->normalizePath($path);
        $name = $options['name'] ?? $this->projectName($repoPath);
        $integrityLevel = (int) ($options['integrity_level'] ?? 2);
        $commitLimit = (int) ($options['commit_limit'] ?? 20);
        $issueLimit = (int) ($options['issue_limit'] ?? 50);
        $prLimit = (int) ($options['pr_limit'] ?? 50);

        return DB::transaction(function () use ($repoPath, $name, $integrityLevel, $commitLimit, $issueLimit, $prLimit): array {
            $readme = $this->readFile($repoPath, 'README.md');
            $description = $this->firstParagraph($readme)
                ?? "Imported from {$repoPath}.";

            $project = Project::query()->updateOrCreate(
                ['name' => $name],
                [
                    'description' => $description,
                    'integrity_level' => $integrityLevel,
                ],
            );

            $sourceCount = $this->ingestSources($project, $repoPath);
            $requirementCount = $this->ingestJourneyRequirements($project, $repoPath);
            $this->upsertProjectPlan($project, $repoPath);
            $issueCount = $this->ingestGitHubIssues($project, $repoPath, $issueLimit);
            $prCount = $this->ingestGitHubPullRequests($project, $repoPath, $prLimit);
            $workItemCount = $issueCount + $this->ingestCommits($project, $repoPath, $commitLimit);

            return [
                'project_id' => $project->id,
                'project_name' => $project->name,
                'path' => $repoPath,
                'sources' => $sourceCount,
                'requirements' => $requirementCount,
                'issues' => $issueCount,
                'pull_requests' => $prCount,
                'work_items' => $workItemCount,
            ];
        });
    }

    private function normalizePath(string $path): string
    {
        $expanded = str_starts_with($path, '~')
            ? ($_SERVER['HOME'] ?? getenv('HOME')).substr($path, 1)
            : $path;

        $real = realpath($expanded);
        if (! $real || ! is_dir($real)) {
            throw new RuntimeException("Project path [{$path}] does not exist or is not a directory.");
        }

        return $real;
    }

    private function projectName(string $repoPath): string
    {
        $composer = $this->jsonFile($repoPath, 'composer.json');
        if (is_string($composer['name'] ?? null)) {
            return Str::of($composer['name'])->afterLast('/')->headline()->toString();
        }

        return Str::of(basename($repoPath))->replace(['-', '_'], ' ')->headline()->toString();
    }

    private function ingestSources(Project $project, string $repoPath): int
    {
        $paths = collect(['README.md', 'DESIGN.md', 'TODOS.md'])
            ->merge($this->markdownFiles($repoPath, 'docs'))
            ->unique()
            ->filter(fn (string $relativePath): bool => is_file($repoPath.'/'.$relativePath));

        $count = 0;
        foreach ($paths as $relativePath) {
            $body = $this->readFile($repoPath, $relativePath);
            Source::query()->updateOrCreate(
                [
                    'project_id' => $project->id,
                    'uri' => "file://{$repoPath}/{$relativePath}",
                ],
                [
                    'kind' => 'doc',
                    'title' => $this->heading($body) ?? $this->titleFromPath($relativePath),
                    'body' => $body,
                    'external_ref' => $relativePath,
                ],
            );
            $count++;
        }

        return $count;
    }

    private function ingestJourneyRequirements(Project $project, string $repoPath): int
    {
        $journeyPaths = $this->markdownFiles($repoPath, 'docs/reference/journeys')
            ->reject(fn (string $path): bool => str_ends_with($path, '00-overview.md') || str_ends_with($path, 'backlog.md'));

        $count = 0;
        foreach ($journeyPaths as $relativePath) {
            $body = $this->readFile($repoPath, $relativePath);
            $title = $this->heading($body) ?? $this->titleFromPath($relativePath);
            $criteria = $this->checklistItemsUnder($body, 'Acceptance Criteria');

            Requirement::query()->updateOrCreate(
                [
                    'project_id' => $project->id,
                    'source' => $relativePath,
                ],
                [
                    'doc' => 'srs',
                    'type' => 'functional',
                    'text' => "The system shall support the {$title} journey described in {$relativePath}.",
                    'rationale' => $this->section($body, 'User Story'),
                    'acceptance_criteria' => $criteria ?: null,
                    'priority' => 'medium',
                    'tags' => ['ingested', 'journey'],
                ],
            );
            $count++;
        }

        return $count;
    }

    private function upsertProjectPlan(Project $project, string $repoPath): void
    {
        $readme = $this->readFile($repoPath, 'README.md');
        $design = $this->readFile($repoPath, 'DESIGN.md');
        $todos = $this->readFile($repoPath, 'TODOS.md');

        ProjectPlan::query()->updateOrCreate(
            ['project_id' => $project->id],
            [
                'status' => 'draft',
                'scope_summary' => $this->firstParagraph($readme),
                'objectives' => $this->section($design, 'Design Principles'),
                'deliverables_summary' => $this->section($todos, 'Current Focus')
                    ?? $this->section($todos, 'Implementation Order'),
                'approach' => 'Imported from repository documentation and recent git history for initial planning traceability.',
                'constraints' => $this->section($design, 'Anti-references'),
            ],
        );
    }

    private function ingestCommits(Project $project, string $repoPath, int $limit): int
    {
        if (! is_dir($repoPath.'/.git') || $limit < 1) {
            return 0;
        }

        $process = new Process([
            'git',
            'log',
            "--max-count={$limit}",
            '--pretty=format:%H%x1f%h%x1f%s',
        ], $repoPath);
        $process->run();

        if (! $process->isSuccessful()) {
            return 0;
        }

        $count = 0;
        foreach (explode("\n", trim($process->getOutput())) as $line) {
            if ($line === '') {
                continue;
            }

            [$sha, $shortSha, $subject] = array_pad(explode("\x1f", $line, 3), 3, '');
            if ($sha === '' || $subject === '') {
                continue;
            }

            $existingLink = WorkItemDeliveryLink::query()
                ->where('type', 'commit')
                ->where('ref', $sha)
                ->whereHas('workItem', fn ($query) => $query->where('project_id', $project->id))
                ->first();

            if ($existingLink) {
                continue;
            }

            $workItem = $this->workItemForCommit($project, $subject)
                ?? WorkItem::query()->create([
                    'project_id' => $project->id,
                    'kind' => 'task',
                    'name' => Str::limit($subject, 255, ''),
                    'description' => "Imported from git commit {$shortSha}.",
                    'status' => 'done',
                ]);

            WorkItemDeliveryLink::query()->create([
                'work_item_id' => $workItem->id,
                'type' => 'commit',
                'ref' => $sha,
                'description' => $subject,
            ]);

            $count++;
        }

        return $count;
    }

    private function workItemForCommit(Project $project, string $subject): ?WorkItem
    {
        if (! preg_match('/\(#(\d+)\)/', $subject, $matches)) {
            return null;
        }

        return $this->workItemForIssueNumber($project, (int) $matches[1]);
    }

    private function workItemForIssueNumber(Project $project, int $issueNumber): ?WorkItem
    {
        return WorkItem::query()
            ->where('project_id', $project->id)
            ->where('name', 'like', "#{$issueNumber} %")
            ->first();
    }

    private function ingestGitHubIssues(Project $project, string $repoPath, int $limit): int
    {
        $repository = $this->githubRepository($repoPath);
        if (! $repository || $limit < 1) {
            return 0;
        }

        $process = new Process([
            'gh',
            'issue',
            'list',
            '--repo',
            $repository,
            '--state',
            'all',
            '--limit',
            (string) $limit,
            '--json',
            'number,title,state,body,url,labels,milestone',
        ], $repoPath);
        $process->setTimeout(60);
        $process->run();

        if (! $process->isSuccessful()) {
            return 0;
        }

        $issues = json_decode($process->getOutput(), true);
        if (! is_array($issues)) {
            return 0;
        }

        $count = 0;
        foreach ($issues as $issue) {
            if (! is_array($issue) || ! isset($issue['number'], $issue['title'], $issue['url'])) {
                continue;
            }

            $number = (int) $issue['number'];
            $title = trim((string) $issue['title']);
            $url = (string) $issue['url'];
            $labels = collect($issue['labels'] ?? [])
                ->pluck('name')
                ->filter()
                ->values()
                ->all();
            $milestone = is_array($issue['milestone'] ?? null)
                ? ($issue['milestone']['title'] ?? null)
                : null;

            Source::query()->updateOrCreate(
                [
                    'project_id' => $project->id,
                    'uri' => $url,
                ],
                [
                    'kind' => 'ticket',
                    'title' => "#{$number} {$title}",
                    'body' => $issue['body'] ?? null,
                    'external_ref' => "github:{$repository}#{$number}",
                ],
            );

            WorkItem::query()->updateOrCreate(
                [
                    'project_id' => $project->id,
                    'name' => "#{$number} {$title}",
                ],
                [
                    'kind' => 'task',
                    'description' => $this->issueDescription($url, $labels, $milestone),
                    'status' => strtolower((string) ($issue['state'] ?? 'open')) === 'closed' ? 'done' : 'todo',
                ],
            );

            $count++;
        }

        return $count;
    }

    private function ingestGitHubPullRequests(Project $project, string $repoPath, int $limit): int
    {
        $repository = $this->githubRepository($repoPath);
        if (! $repository || $limit < 1) {
            return 0;
        }

        $process = new Process([
            'gh',
            'pr',
            'list',
            '--repo',
            $repository,
            '--state',
            'merged',
            '--limit',
            (string) $limit,
            '--json',
            'number,title,body,url,mergeCommit,closingIssuesReferences',
        ], $repoPath);
        $process->setTimeout(60);
        $process->run();

        if (! $process->isSuccessful()) {
            return 0;
        }

        $pullRequests = json_decode($process->getOutput(), true);
        if (! is_array($pullRequests)) {
            return 0;
        }

        $count = 0;
        foreach ($pullRequests as $pullRequest) {
            if (! is_array($pullRequest) || ! isset($pullRequest['number'], $pullRequest['title'], $pullRequest['url'])) {
                continue;
            }

            $linked = false;
            foreach ($this->issueNumbersFromPullRequest($pullRequest) as $issueNumber) {
                $workItem = $this->workItemForIssueNumber($project, $issueNumber);
                if (! $workItem) {
                    continue;
                }

                $this->upsertDeliveryLink($workItem, [
                    'type' => 'pull_request',
                    'ref' => '#'.(int) $pullRequest['number'],
                    'url' => (string) $pullRequest['url'],
                    'description' => trim((string) $pullRequest['title']),
                ]);

                $mergeCommit = $pullRequest['mergeCommit']['oid'] ?? null;
                if (is_string($mergeCommit) && $mergeCommit !== '') {
                    $this->upsertDeliveryLink($workItem, [
                        'type' => 'commit',
                        'ref' => $mergeCommit,
                        'description' => "Merge commit for PR #{$pullRequest['number']}: ".trim((string) $pullRequest['title']),
                    ]);
                }

                $linked = true;
            }

            if (! $linked) {
                continue;
            }

            Source::query()->updateOrCreate(
                [
                    'project_id' => $project->id,
                    'uri' => (string) $pullRequest['url'],
                ],
                [
                    'kind' => 'ticket',
                    'title' => "#{$pullRequest['number']} {$pullRequest['title']}",
                    'body' => $pullRequest['body'] ?? null,
                    'external_ref' => "github:{$repository}!{$pullRequest['number']}",
                ],
            );
            $count++;
        }

        return $count;
    }

    /**
     * @param  array<string, mixed>  $pullRequest
     * @return array<int, int>
     */
    private function issueNumbersFromPullRequest(array $pullRequest): array
    {
        $numbers = collect($pullRequest['closingIssuesReferences'] ?? [])
            ->pluck('number')
            ->filter(fn ($number): bool => is_numeric($number))
            ->map(fn ($number): int => (int) $number);

        preg_match_all('/(?:close[sd]?|fix(?:e[sd])?|resolve[sd]?|track(?:s|ed)?|refs?|references?)\s+#(\d+)/i', (string) ($pullRequest['body'] ?? ''), $matches);

        return $numbers
            ->merge(collect($matches[1] ?? [])->map(fn (string $number): int => (int) $number))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array{type: string, ref: string, url?: string|null, description?: string|null}  $attributes
     */
    private function upsertDeliveryLink(WorkItem $workItem, array $attributes): void
    {
        WorkItemDeliveryLink::query()->updateOrCreate(
            [
                'work_item_id' => $workItem->id,
                'type' => $attributes['type'],
                'ref' => $attributes['ref'],
            ],
            [
                'url' => $attributes['url'] ?? null,
                'description' => $attributes['description'] ?? null,
            ],
        );
    }

    private function githubRepository(string $repoPath): ?string
    {
        if (! is_dir($repoPath.'/.git')) {
            return null;
        }

        $process = new Process(['git', 'remote', 'get-url', 'origin'], $repoPath);
        $process->run();

        if (! $process->isSuccessful()) {
            return null;
        }

        $remote = trim($process->getOutput());
        if (preg_match('/github\.com[:\/]([^\/]+\/[^\/\.]+)(?:\.git)?$/', $remote, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * @param  array<int, string>  $labels
     */
    private function issueDescription(string $url, array $labels, ?string $milestone): string
    {
        $parts = ["Imported from GitHub issue {$url}."];
        if ($labels !== []) {
            $parts[] = 'Labels: '.implode(', ', $labels).'.';
        }
        if ($milestone) {
            $parts[] = "Milestone: {$milestone}.";
        }

        return implode("\n", $parts);
    }

    /**
     * @return Collection<int, string>
     */
    private function markdownFiles(string $repoPath, string $relativeDirectory): Collection
    {
        $directory = $repoPath.'/'.$relativeDirectory;
        if (! is_dir($directory)) {
            return collect();
        }

        $finder = Finder::create()
            ->files()
            ->name('*.md')
            ->in($directory)
            ->ignoreDotFiles(true)
            ->ignoreVCS(true)
            ->sortByName();

        return collect(iterator_to_array($finder, false))
            ->map(fn ($file): string => Str::of($file->getRealPath())->after($repoPath.'/')->toString());
    }

    private function readFile(string $repoPath, string $relativePath): ?string
    {
        $path = $repoPath.'/'.$relativePath;

        return is_file($path) ? file_get_contents($path) : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function jsonFile(string $repoPath, string $relativePath): array
    {
        $contents = $this->readFile($repoPath, $relativePath);
        if (! $contents) {
            return [];
        }

        return json_decode($contents, true) ?: [];
    }

    private function heading(?string $markdown): ?string
    {
        if (! $markdown) {
            return null;
        }

        if (preg_match('/^#\s+(.+)$/m', $markdown, $matches)) {
            return trim($matches[1]);
        }

        return null;
    }

    private function titleFromPath(string $path): string
    {
        return Str::of(pathinfo($path, PATHINFO_FILENAME))
            ->replaceMatches('/^\d+-/', '')
            ->replace(['-', '_'], ' ')
            ->headline()
            ->toString();
    }

    private function firstParagraph(?string $markdown): ?string
    {
        if (! $markdown) {
            return null;
        }

        foreach (preg_split('/\R{2,}/', trim($markdown)) ?: [] as $block) {
            $block = trim(preg_replace('/^#.*\R?/', '', $block) ?? '');
            if ($block !== '' && ! str_starts_with($block, '- ') && ! str_starts_with($block, '#')) {
                return Str::limit($block, 500, '');
            }
        }

        return null;
    }

    private function section(?string $markdown, string $heading): ?string
    {
        if (! $markdown) {
            return null;
        }

        $pattern = '/^##\s+'.preg_quote($heading, '/').'\s*$(.*?)(?=^##\s+|\z)/ms';
        if (! preg_match($pattern, $markdown, $matches)) {
            return null;
        }

        return trim($matches[1]) ?: null;
    }

    /**
     * @return array<int, string>
     */
    private function checklistItemsUnder(?string $markdown, string $heading): array
    {
        $section = $this->section($markdown, $heading);
        if (! $section) {
            return [];
        }

        preg_match_all('/^\s*-\s+\[[ xX-]\]\s+(.+)$/m', $section, $matches);

        return collect($matches[1] ?? [])
            ->map(fn (string $item): string => trim($item))
            ->filter()
            ->values()
            ->all();
    }
}
