<?php

namespace App\Growth\Export;

use App\Models\Project;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;

class ProjectExporter
{
    /**
     * @return array<string, mixed>
     */
    public function export(string $projectReference, string $path): array
    {
        $project = $this->findProject($projectReference);
        $target = $this->normalizeTargetPath($path);

        $project->load([
            'projectPlan',
            'requirements' => fn ($query) => $query->orderBy('doc')->orderBy('type')->orderBy('source')->orderBy('id'),
            'sources' => fn ($query) => $query->orderBy('kind')->orderBy('title'),
            'workItems' => fn ($query) => $query->with([
                'requirements:id,doc,type,text,source',
                'deliveryLinks',
                'dependencies:id,name',
                'raciRoles:id,name',
                'responsibleRole:id,name',
            ])->orderBy('status')->orderBy('name'),
        ]);

        File::ensureDirectoryExists($target);

        $files = [
            'manifest.json' => $this->json($this->manifest($project)),
            'project.md' => $this->projectMarkdown($project),
            'requirements.md' => $this->requirementsMarkdown($project),
            'work-items.md' => $this->workItemsMarkdown($project),
            'sources.json' => $this->json($this->sources($project)),
            'traceability.json' => $this->json($this->traceability($project)),
        ];

        foreach ($files as $filename => $contents) {
            File::put($target.'/'.$filename, $contents);
        }

        return [
            'project_id' => $project->id,
            'project_name' => $project->name,
            'path' => $target,
            'files' => array_keys($files),
            'requirements' => $project->requirements->count(),
            'sources' => $project->sources->count(),
            'work_items' => $project->workItems->count(),
        ];
    }

    private function findProject(string $reference): Project
    {
        $project = Project::query()
            ->where('id', $reference)
            ->orWhere('name', $reference)
            ->first();

        if (! $project) {
            throw new RuntimeException("Project [{$reference}] not found.");
        }

        return $project;
    }

    private function normalizeTargetPath(string $path): string
    {
        $expanded = str_starts_with($path, '~')
            ? ($_SERVER['HOME'] ?? getenv('HOME')).substr($path, 1)
            : $path;

        $parent = dirname($expanded);
        $realParent = realpath($parent);
        if (! $realParent || ! is_dir($realParent)) {
            throw new RuntimeException("Export parent directory [{$parent}] does not exist.");
        }

        return $realParent.'/'.basename($expanded);
    }

    /**
     * @return array<string, mixed>
     */
    private function manifest(Project $project): array
    {
        return [
            'format' => 'growth-workbench.project-export.v1',
            'generated_at' => now()->toIso8601String(),
            'project' => [
                'id' => $project->id,
                'name' => $project->name,
                'description' => $project->description,
                'rigor_level' => $project->rigor_level,
            ],
            'counts' => [
                'requirements' => $project->requirements->count(),
                'sources' => $project->sources->count(),
                'work_items' => $project->workItems->count(),
            ],
            'files' => [
                'project.md',
                'requirements.md',
                'work-items.md',
                'sources.json',
                'traceability.json',
            ],
        ];
    }

    private function projectMarkdown(Project $project): string
    {
        $plan = $project->projectPlan;
        $md = "# {$project->name}\n\n";
        $md .= "- **Project id:** `{$project->id}`\n";
        $md .= "- **Rigor level:** {$project->rigor_level}\n";
        $md .= '- **Plan status:** '.($plan?->status ?? 'missing')."\n\n";

        if ($project->description) {
            $md .= "## Description\n\n{$project->description}\n\n";
        }

        $sections = [
            'Scope' => $plan?->scope_summary,
            'Objectives' => $plan?->objectives,
            'Deliverables' => $plan?->deliverables_summary,
            'Approach' => $plan?->approach,
            'Assumptions' => $plan?->assumptions,
            'Constraints' => $plan?->constraints,
            'Budget' => $plan?->budget_summary,
        ];

        foreach ($sections as $heading => $body) {
            $md .= "## {$heading}\n\n";
            $md .= $body ? trim($body)."\n\n" : "_None recorded._\n\n";
        }

        return $md;
    }

    private function requirementsMarkdown(Project $project): string
    {
        $md = "# Requirements — {$project->name}\n\n";
        if ($project->requirements->isEmpty()) {
            return $md."_None recorded._\n";
        }

        foreach ($project->requirements->groupBy('doc') as $doc => $docRequirements) {
            $md .= "## {$doc}\n\n";
            foreach ($docRequirements->groupBy('type') as $type => $requirements) {
                $md .= "### {$type}\n\n";
                foreach ($requirements as $requirement) {
                    $priority = $requirement->priority ? " _({$requirement->priority})_" : '';
                    $md .= "- **[{$requirement->id}]**{$priority} {$requirement->text}\n";
                    if ($requirement->source) {
                        $md .= "  - Source: `{$requirement->source}`\n";
                    }
                    if ($requirement->rationale) {
                        $md .= '  - Rationale: '.Str::of($requirement->rationale)->replace("\n", ' ')->trim()."\n";
                    }
                    if ($requirement->acceptance_criteria) {
                        $md .= "  - Acceptance criteria:\n";
                        foreach ($requirement->acceptance_criteria as $criterion) {
                            $md .= "    - {$criterion}\n";
                        }
                    }
                }
                $md .= "\n";
            }
        }

        return $md;
    }

    private function workItemsMarkdown(Project $project): string
    {
        $md = "# Work Items — {$project->name}\n\n";
        if ($project->workItems->isEmpty()) {
            return $md."_None recorded._\n";
        }

        [$deliveryOnly, $plannedWork] = $project->workItems
            ->partition(fn ($workItem): bool => $this->isUnmatchedCommitEvidence($workItem));

        foreach ($plannedWork->groupBy('status') as $status => $workItems) {
            $md .= "## {$status}\n\n";
            foreach ($workItems as $workItem) {
                $md .= $this->workItemMarkdown($workItem);
            }
            $md .= "\n";
        }

        if ($deliveryOnly->isNotEmpty()) {
            $md .= "## Delivery Evidence Without Matched Ticket\n\n";
            $md .= "These commits were imported from git history but did not match an imported GitHub issue number.\n\n";
            foreach ($deliveryOnly as $workItem) {
                $md .= $this->workItemMarkdown($workItem);
            }
            $md .= "\n";
        }

        return $md;
    }

    private function workItemMarkdown($workItem): string
    {
        $md = "- **[{$workItem->kind}] {$workItem->name}**";
        if ($workItem->responsibleRole) {
            $md .= " _(owner: {$workItem->responsibleRole->name})_";
        }
        $md .= "\n";
        if ($workItem->description) {
            $md .= '  - Description: '.Str::of($workItem->description)->replace("\n", ' ')->trim()."\n";
        }
        if ($workItem->requirements->isNotEmpty()) {
            $requirements = $workItem->requirements
                ->map(fn ($requirement) => "{$requirement->doc}/{$requirement->type}: {$requirement->source}")
                ->implode('; ');
            $md .= "  - Requirements: {$requirements}\n";
        }
        if ($workItem->dependencies->isNotEmpty()) {
            $md .= '  - Depends on: '.$workItem->dependencies->pluck('name')->implode(', ')."\n";
        }
        if ($workItem->raciRoles->isNotEmpty()) {
            $assignments = $workItem->raciRoles
                ->map(fn ($role) => strtoupper($role->pivot->raci).': '.$role->name)
                ->implode('; ');
            $md .= "  - RACI: {$assignments}\n";
        }
        if ($workItem->deliveryLinks->isNotEmpty()) {
            if ($workItem->deliveryLinks->count() > 3) {
                $md .= "  - Delivery evidence:\n";
                foreach ($workItem->deliveryLinks as $link) {
                    $md .= "    - {$link->type}: {$link->ref}".($link->url ? " ({$link->url})" : '')."\n";
                }
            } else {
                $links = $workItem->deliveryLinks
                    ->map(fn ($link) => "{$link->type}: {$link->ref}".($link->url ? " ({$link->url})" : ''))
                    ->implode('; ');
                $md .= "  - Delivery evidence: {$links}\n";
            }
        }

        return $md;
    }

    private function isUnmatchedCommitEvidence($workItem): bool
    {
        return $workItem->requirements->isEmpty()
            && $workItem->deliveryLinks->contains(fn ($link): bool => $link->type === 'commit')
            && Str::of($workItem->description ?? '')->startsWith('Imported from git commit')
            && ! Str::of($workItem->name)->startsWith('#');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function sources(Project $project): array
    {
        return $project->sources
            ->map(fn ($source): array => [
                'id' => $source->id,
                'kind' => $source->kind,
                'title' => $source->title,
                'uri' => $source->uri,
                'external_ref' => $source->external_ref,
                'body' => $source->body,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function traceability(Project $project): array
    {
        return [
            'requirements' => $project->requirements
                ->map(fn ($requirement): array => [
                    'id' => $requirement->id,
                    'doc' => $requirement->doc,
                    'type' => $requirement->type,
                    'source' => $requirement->source,
                    'work_items' => $requirement->workItems()->pluck('work_items.id')->all(),
                ])
                ->values()
                ->all(),
            'work_items' => $project->workItems
                ->map(fn ($workItem): array => [
                    'id' => $workItem->id,
                    'name' => $workItem->name,
                    'status' => $workItem->status,
                    'requirements' => $workItem->requirements->pluck('id')->all(),
                    'delivery_links' => $workItem->deliveryLinks
                        ->map(fn ($link): array => [
                            'id' => $link->id,
                            'type' => $link->type,
                            'ref' => $link->ref,
                            'url' => $link->url,
                        ])
                        ->values()
                        ->all(),
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @param  array<string, mixed>|array<int, array<string, mixed>>  $data
     */
    private function json(array $data): string
    {
        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n";
    }
}
