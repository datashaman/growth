<?php

namespace App\Growth\Manifest;

use App\Models\Concern;
use App\Models\Project;
use App\Models\Requirement;
use App\Models\Stakeholder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Applies a Growth project manifest (project + stakeholders + concerns + capabilities)
 * inside a single transaction. Supports three modes (fail | merge | replace) and a
 * dry-run that always rolls back.
 *
 * Returned report:
 *   {
 *     'project_id': string,
 *     'mode': 'fail'|'merge'|'replace',
 *     'dry_run': bool,
 *     'counts': {
 *       'project_created': bool, 'project_updated': bool,
 *       'stakeholders_created': int, 'stakeholders_updated': int, 'stakeholders_deleted': int,
 *       'concerns_created': int,     'concerns_updated': int,     'concerns_deleted': int,
 *       'capabilities_created': int, 'capabilities_updated': int, 'capabilities_deleted': int,
 *     },
 *     'slugs': { 'capabilities': { '<slug>': '<ulid>', ... }, 'stakeholders': {...}, 'concerns': {...} },
 *     'drift': [ {'entity': 'capability', 'slug': '...', 'exported_at': '...', 'current_at': '...'} ],
 *   }
 */
class ManifestApplier
{
    /**
     * @param  array<string,mixed>  $manifest
     * @param  'fail'|'merge'|'replace'  $mode
     * @return array<string,mixed>
     */
    public function apply(array $manifest, string $mode = 'fail', bool $dryRun = false, ?string $confirm = null, ?int $userId = null): array
    {
        $userId ??= (int) auth()->id();

        return DB::transaction(function () use ($manifest, $mode, $dryRun, $confirm, $userId): array {
            $report = $this->run($manifest, $mode, $confirm, $userId);

            if ($dryRun) {
                $report['dry_run'] = true;
                DB::rollBack();
            } else {
                $report['dry_run'] = false;
            }

            return $report;
        });
    }

    /**
     * @param  array<string,mixed>  $manifest
     * @return array<string,mixed>
     */
    private function run(array $manifest, string $mode, ?string $confirm, int $userId): array
    {
        $projectInput = $manifest['project'] ?? [];
        $existingProject = isset($projectInput['id']) ? Project::find($projectInput['id']) : null;

        if (isset($projectInput['id']) && ! $existingProject) {
            throw new RuntimeException("Project [{$projectInput['id']}] not found.");
        }

        $effectiveMode = $mode;
        if ($mode === 'replace' && ! $existingProject) {
            $effectiveMode = 'fail';
        }

        if ($effectiveMode === 'replace' && $confirm !== $existingProject?->name) {
            throw new RuntimeException(
                "Replace mode requires `confirm` to match the project's exact name. Project is named [{$existingProject?->name}]."
            );
        }

        $drift = [];
        $counts = [
            'project_created' => false, 'project_updated' => false,
            'stakeholders_created' => 0, 'stakeholders_updated' => 0, 'stakeholders_deleted' => 0,
            'concerns_created' => 0,     'concerns_updated' => 0,     'concerns_deleted' => 0,
            'capabilities_created' => 0, 'capabilities_updated' => 0, 'capabilities_deleted' => 0,
        ];
        $slugs = ['capabilities' => [], 'stakeholders' => [], 'concerns' => []];

        $project = $this->applyProject($projectInput, $existingProject, $effectiveMode, $userId, $counts, $drift);

        if ($effectiveMode === 'replace') {
            $counts['capabilities_deleted'] = Requirement::where('project_id', $project->id)->count();
            Requirement::where('project_id', $project->id)->delete();
            $counts['concerns_deleted'] = Concern::where('project_id', $project->id)->count();
            Concern::where('project_id', $project->id)->delete();
            $counts['stakeholders_deleted'] = Stakeholder::where('project_id', $project->id)->count();
            Stakeholder::where('project_id', $project->id)->delete();
        }

        foreach (($manifest['stakeholders'] ?? []) as $row) {
            $stakeholder = $this->applyStakeholder($row, $project->id, $effectiveMode, $counts, $drift);
            if (! empty($row['slug'])) {
                $slugs['stakeholders'][$row['slug']] = $stakeholder->id;
            }
        }

        foreach (($manifest['concerns'] ?? []) as $row) {
            $concern = $this->applyConcern($row, $project->id, $effectiveMode, $slugs, $counts, $drift);
            if (! empty($row['slug'])) {
                $slugs['concerns'][$row['slug']] = $concern->id;
            }
        }

        foreach (($manifest['capabilities'] ?? []) as $row) {
            $capability = $this->applyCapability($row, $project->id, $effectiveMode, $counts, $drift);
            $slugs['capabilities'][$capability->slug] = $capability->id;
        }

        return [
            'project_id' => $project->id,
            'mode' => $mode,
            'effective_mode' => $effectiveMode,
            'counts' => $counts,
            'slugs' => $slugs,
            'drift' => $drift,
        ];
    }

    /**
     * @param  array<string,mixed>  $input
     * @param  array<string,mixed>  $counts
     * @param  array<int,array<string,mixed>>  $drift
     */
    private function applyProject(array $input, ?Project $existing, string $mode, int $userId, array &$counts, array &$drift): Project
    {
        $fields = array_intersect_key($input, array_flip(['name', 'description', 'rigor_level', 'status']));

        if ($existing) {
            $this->checkDrift('project', $existing->updated_at, $input['_exported_at'] ?? null, $existing->id, $drift);

            if ($mode === 'fail') {
                $differs = false;
                foreach ($fields as $k => $v) {
                    if ($existing->{$k} !== $v) {
                        $differs = true;
                        break;
                    }
                }
                if ($differs) {
                    throw new RuntimeException(
                        "Project [{$existing->name}] already exists; fail mode aborts on any difference. Use merge or replace mode to update."
                    );
                }
            } else {
                $existing->fill($fields)->save();
                $counts['project_updated'] = true;
            }

            return $existing;
        }

        $project = Project::create($fields + [
            'rigor_level' => $fields['rigor_level'] ?? 2,
            'status' => $fields['status'] ?? 'active',
            'user_id' => $userId,
        ]);
        $counts['project_created'] = true;

        return $project;
    }

    /**
     * @param  array<string,mixed>  $input
     * @param  array<string,mixed>  $counts
     * @param  array<int,array<string,mixed>>  $drift
     */
    private function applyStakeholder(array $input, string $projectId, string $mode, array &$counts, array &$drift): Stakeholder
    {
        $fields = array_intersect_key($input, array_flip(['name', 'role', 'kind', 'description']));
        $existing = Stakeholder::where('project_id', $projectId)->where('name', $fields['name'])->first();

        if ($existing) {
            $this->checkDrift('stakeholder', $existing->updated_at, $input['_exported_at'] ?? null, $existing->id, $drift);

            if ($mode === 'fail') {
                $this->failOnCollision('stakeholder', $existing, $fields);

                return $existing;
            }

            $existing->fill($fields)->save();
            $counts['stakeholders_updated']++;

            return $existing;
        }

        $created = Stakeholder::create($fields + ['project_id' => $projectId]);
        $counts['stakeholders_created']++;

        return $created;
    }

    /**
     * @param  array<string,mixed>  $input
     * @param  array<string,array<string,string>>  $slugs
     * @param  array<string,mixed>  $counts
     * @param  array<int,array<string,mixed>>  $drift
     */
    private function applyConcern(array $input, string $projectId, string $mode, array $slugs, array &$counts, array &$drift): Concern
    {
        $fields = array_intersect_key($input, array_flip(['text', 'viewpoint_hints']));

        if (isset($input['raised_by'])) {
            $stakeholderId = $slugs['stakeholders'][$input['raised_by']]
                ?? Stakeholder::where('project_id', $projectId)->where('name', $input['raised_by'])->value('id');

            if ($stakeholderId === null) {
                throw new RuntimeException("Concern references unknown stakeholder [{$input['raised_by']}].");
            }
            $fields['raised_by_stakeholder_id'] = $stakeholderId;
        }

        $existing = Concern::where('project_id', $projectId)->where('text', $fields['text'])->first();

        if ($existing) {
            $this->checkDrift('concern', $existing->updated_at, $input['_exported_at'] ?? null, $existing->id, $drift);

            if ($mode === 'fail') {
                $this->failOnCollision('concern', $existing, $fields);

                return $existing;
            }

            $existing->fill($fields)->save();
            $counts['concerns_updated']++;

            return $existing;
        }

        $created = Concern::create($fields + ['project_id' => $projectId]);
        $counts['concerns_created']++;

        return $created;
    }

    /**
     * @param  array<string,mixed>  $input
     * @param  array<string,mixed>  $counts
     * @param  array<int,array<string,mixed>>  $drift
     */
    private function applyCapability(array $input, string $projectId, string $mode, array &$counts, array &$drift): Requirement
    {
        $fields = array_intersect_key($input, array_flip([
            'slug', 'doc', 'type', 'text', 'rationale', 'acceptance_criteria', 'source', 'priority', 'tags',
        ]));
        $fields += ['doc' => 'srs', 'type' => 'functional'];

        $existing = Requirement::where('project_id', $projectId)->where('slug', $fields['slug'])->first();

        if ($existing) {
            $this->checkDrift('capability', $existing->updated_at, $input['_exported_at'] ?? null, $existing->slug, $drift);

            if ($mode === 'fail') {
                $this->failOnCollision('capability', $existing, $fields);

                return $existing;
            }

            $existing->fill($fields)->save();
            $counts['capabilities_updated']++;

            return $existing;
        }

        $created = Requirement::create($fields + ['project_id' => $projectId]);
        $counts['capabilities_created']++;

        return $created;
    }

    /**
     * @param  array<string,mixed>  $fields
     */
    private function failOnCollision(string $entity, $existing, array $fields): void
    {
        $differs = false;
        foreach ($fields as $k => $v) {
            $current = $existing->{$k};
            if ($current instanceof \DateTimeInterface) {
                continue;
            }
            if ($current !== $v) {
                $differs = true;
                break;
            }
        }
        if ($differs) {
            $key = $existing->slug ?? $existing->name ?? $existing->text ?? $existing->id;
            throw new RuntimeException(
                "{$entity} [{$key}] already exists with different content; fail mode aborts on any difference. Use merge or replace mode to update."
            );
        }
    }

    /**
     * @param  array<int,array<string,mixed>>  $drift
     */
    private function checkDrift(string $entity, ?Carbon $currentAt, ?string $exportedAt, string $identifier, array &$drift): void
    {
        if ($exportedAt === null || $currentAt === null) {
            return;
        }
        $exported = Carbon::parse($exportedAt);
        if ($currentAt->greaterThan($exported)) {
            $drift[] = [
                'entity' => $entity,
                'identifier' => $identifier,
                'exported_at' => $exported->toIso8601String(),
                'current_at' => $currentAt->toIso8601String(),
            ];
        }
    }
}
