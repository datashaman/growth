<?php

namespace App\Growth\Manifest;

use App\Models\Concern;
use App\Models\CustomViewpoint;
use App\Models\DesignElement;
use App\Models\DesignView;
use App\Models\Project;
use App\Models\Requirement;
use App\Models\Stakeholder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Applies a Growth project manifest (project + stakeholders + concerns + capabilities +
 * architecture viewpoints/views/elements) inside a single transaction. Supports three
 * modes (fail | merge | replace) and a dry-run that always rolls back.
 *
 * Returned report:
 *   {
 *     'project_id': string,
 *     'mode': 'fail'|'merge'|'replace',
 *     'dry_run': bool,
 *     'counts': { '<entity>_created|_updated|_deleted': int|bool, ... },
 *     'slugs': { 'capabilities': {...}, 'stakeholders': {...}, 'concerns': {...},
 *                'viewpoints': {...}, 'views': {...}, 'elements': {...} },
 *     'drift': [ {'entity': '...', 'identifier': '...', 'exported_at': '...', 'current_at': '...'} ],
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
            'viewpoints_created' => 0,   'viewpoints_updated' => 0,   'viewpoints_deleted' => 0,
            'views_created' => 0,        'views_updated' => 0,        'views_deleted' => 0,
            'elements_created' => 0,     'elements_updated' => 0,     'elements_deleted' => 0,
        ];
        $slugs = [
            'capabilities' => [], 'stakeholders' => [], 'concerns' => [],
            'viewpoints' => [], 'views' => [], 'elements' => [],
        ];

        $project = $this->applyProject($projectInput, $existingProject, $effectiveMode, $userId, $counts, $drift);

        if ($effectiveMode === 'replace') {
            $viewIds = DesignView::where('project_id', $project->id)->pluck('id');
            $counts['elements_deleted'] = DesignElement::whereIn('design_view_id', $viewIds)->count();
            DesignElement::whereIn('design_view_id', $viewIds)->delete();
            $counts['views_deleted'] = DesignView::where('project_id', $project->id)->count();
            DesignView::where('project_id', $project->id)->delete();
            $counts['viewpoints_deleted'] = CustomViewpoint::where('project_id', $project->id)->count();
            CustomViewpoint::where('project_id', $project->id)->delete();
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

        $architecture = $manifest['architecture'] ?? [];

        foreach (($architecture['viewpoints'] ?? []) as $row) {
            $viewpoint = $this->applyArchitectureViewpoint($row, $project->id, $effectiveMode, $counts, $drift);
            if (! empty($row['slug'])) {
                $slugs['viewpoints'][$row['slug']] = $viewpoint->id;
            }
        }

        foreach (($architecture['views'] ?? []) as $row) {
            $view = $this->applyArchitectureView($row, $project->id, $effectiveMode, $slugs, $counts, $drift);
            if (! empty($row['slug'])) {
                $slugs['views'][$row['slug']] = $view->id;
            }

            foreach (($row['elements'] ?? []) as $elementRow) {
                $element = $this->applyArchitectureElement($elementRow, $view->id, $effectiveMode, $counts, $drift);
                if (! empty($elementRow['slug'])) {
                    $slugs['elements'][$elementRow['slug']] = $element->id;
                }
            }
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
     * @param  array<string,mixed>  $input
     * @param  array<string,mixed>  $counts
     * @param  array<int,array<string,mixed>>  $drift
     */
    private function applyArchitectureViewpoint(array $input, string $projectId, string $mode, array &$counts, array &$drift): CustomViewpoint
    {
        $fields = array_intersect_key($input, array_flip(['name', 'concerns', 'element_types', 'languages', 'source']));

        if (in_array($fields['name'] ?? null, DesignView::BUILTIN_VIEWPOINTS, true)) {
            throw new RuntimeException("Viewpoint [{$fields['name']}] collides with a built-in viewpoint name; use a different name for the custom viewpoint.");
        }

        $existing = CustomViewpoint::where('project_id', $projectId)->where('name', $fields['name'])->first();

        if ($existing) {
            $this->checkDrift('viewpoint', $existing->updated_at, $input['_exported_at'] ?? null, $existing->name, $drift);

            if ($mode === 'fail') {
                $this->failOnCollision('viewpoint', $existing, $fields);

                return $existing;
            }

            $existing->fill($fields)->save();
            $counts['viewpoints_updated']++;

            return $existing;
        }

        $created = CustomViewpoint::create($fields + ['project_id' => $projectId]);
        $counts['viewpoints_created']++;

        return $created;
    }

    /**
     * @param  array<string,mixed>  $input
     * @param  array<string,array<string,string>>  $slugs
     * @param  array<string,mixed>  $counts
     * @param  array<int,array<string,mixed>>  $drift
     */
    private function applyArchitectureView(array $input, string $projectId, string $mode, array $slugs, array &$counts, array &$drift): DesignView
    {
        $fields = array_intersect_key($input, array_flip(['viewpoint', 'name', 'description']));

        $viewpointRef = $input['viewpoint'] ?? null;
        if ($viewpointRef === null) {
            throw new RuntimeException("View [{$input['name']}] is missing a `viewpoint` reference.");
        }

        if (in_array($viewpointRef, DesignView::BUILTIN_VIEWPOINTS, true)) {
            $fields['viewpoint'] = $viewpointRef;
        } elseif (isset($slugs['viewpoints'][$viewpointRef])) {
            $fields['viewpoint'] = CustomViewpoint::whereKey($slugs['viewpoints'][$viewpointRef])->value('name');
        } elseif (CustomViewpoint::where('project_id', $projectId)->where('name', $viewpointRef)->exists()) {
            $fields['viewpoint'] = $viewpointRef;
        } else {
            throw new RuntimeException("View [{$input['name']}] references unknown viewpoint [{$viewpointRef}]. Declare a custom viewpoint with that slug/name or use a built-in viewpoint.");
        }

        $concernIds = null;
        if (array_key_exists('addresses_concerns', $input)) {
            $concernIds = [];
            foreach ((array) $input['addresses_concerns'] as $ref) {
                $concernIds[] = $slugs['concerns'][$ref]
                    ?? Concern::where('project_id', $projectId)->where('text', $ref)->value('id')
                    ?? throw new RuntimeException("View [{$input['name']}] references unknown concern [{$ref}].");
            }
        }

        $existing = DesignView::where('project_id', $projectId)->where('name', $fields['name'])->first();

        if ($existing) {
            $this->checkDrift('view', $existing->updated_at, $input['_exported_at'] ?? null, $existing->name, $drift);

            if ($mode === 'fail') {
                $this->failOnCollision('view', $existing, $fields);
            } else {
                $existing->fill($fields)->save();
                $counts['views_updated']++;
            }

            if (is_array($concernIds)) {
                $existing->concerns()->sync($concernIds);
            }

            return $existing;
        }

        $created = DesignView::create($fields + ['project_id' => $projectId]);
        $counts['views_created']++;

        if (is_array($concernIds)) {
            $created->concerns()->sync($concernIds);
        }

        return $created;
    }

    /**
     * @param  array<string,mixed>  $input
     * @param  array<string,mixed>  $counts
     * @param  array<int,array<string,mixed>>  $drift
     */
    private function applyArchitectureElement(array $input, string $viewId, string $mode, array &$counts, array &$drift): DesignElement
    {
        $fields = array_intersect_key($input, array_flip(['kind', 'name', 'type', 'purpose', 'properties']));

        $existing = DesignElement::where('design_view_id', $viewId)->where('name', $fields['name'])->first();

        if ($existing) {
            $this->checkDrift('element', $existing->updated_at, $input['_exported_at'] ?? null, $existing->name, $drift);

            if ($mode === 'fail') {
                $this->failOnCollision('element', $existing, $fields);

                return $existing;
            }

            $existing->fill($fields)->save();
            $counts['elements_updated']++;

            return $existing;
        }

        $created = DesignElement::create($fields + ['design_view_id' => $viewId]);
        $counts['elements_created']++;

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
            if (is_array($current) || is_array($v)) {
                if (json_encode($current) !== json_encode($v)) {
                    $differs = true;
                    break;
                }

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
