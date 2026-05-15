<?php

namespace App\Growth\Manifest;

use App\Models\Concern;
use App\Models\CustomViewpoint;
use App\Models\DesignElement;
use App\Models\DesignView;
use App\Models\Milestone;
use App\Models\Project;
use App\Models\ProjectPlan;
use App\Models\Requirement;
use App\Models\Role;
use App\Models\Stakeholder;
use App\Models\TestCase;
use App\Models\TestPlan;
use App\Models\WorkItem;
use App\Support\WorkspaceContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Applies a Growth project manifest (project + stakeholders + concerns + requirements +
 * architecture viewpoints/views/elements + plan/roles/milestones/work_items +
 * verification plans/cases) inside a single transaction. Supports three modes
 * (fail | merge | replace) and a dry-run that always rolls back.
 *
 * Returned report:
 *   {
 *     'project_id': string,
 *     'mode': 'fail'|'merge'|'replace',
 *     'dry_run': bool,
 *     'counts': { '<entity>_created|_updated|_deleted': int|bool, ... },
 *     'slugs': { 'requirements': {...}, 'stakeholders': {...}, 'concerns': {...},
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
            'requirements_created' => 0, 'requirements_updated' => 0, 'requirements_deleted' => 0,
            'viewpoints_created' => 0,   'viewpoints_updated' => 0,   'viewpoints_deleted' => 0,
            'views_created' => 0,        'views_updated' => 0,        'views_deleted' => 0,
            'elements_created' => 0,     'elements_updated' => 0,     'elements_deleted' => 0,
            'plan_created' => false,     'plan_updated' => false,     'plan_deleted' => false,
            'roles_created' => 0,        'roles_updated' => 0,        'roles_deleted' => 0,
            'milestones_created' => 0,   'milestones_updated' => 0,   'milestones_deleted' => 0,
            'work_items_created' => 0,   'work_items_updated' => 0,   'work_items_deleted' => 0,
            'verification_plans_created' => 0, 'verification_plans_updated' => 0, 'verification_plans_deleted' => 0,
            'verification_cases_created' => 0, 'verification_cases_updated' => 0, 'verification_cases_deleted' => 0,
        ];
        $slugs = [
            'requirements' => [], 'stakeholders' => [], 'concerns' => [],
            'viewpoints' => [], 'views' => [], 'elements' => [],
            'roles' => [], 'milestones' => [], 'work_items' => [],
            'verification_plans' => [], 'verification_cases' => [],
        ];

        $project = $this->applyProject($projectInput, $existingProject, $effectiveMode, $userId, $counts, $drift);

        if ($effectiveMode === 'replace') {
            $planIds = TestPlan::where('project_id', $project->id)->pluck('id');
            $counts['verification_cases_deleted'] = TestCase::whereIn('test_plan_id', $planIds)->count();
            TestCase::whereIn('test_plan_id', $planIds)->delete();
            $counts['verification_plans_deleted'] = TestPlan::where('project_id', $project->id)->count();
            TestPlan::where('project_id', $project->id)->delete();
            $counts['work_items_deleted'] = WorkItem::where('project_id', $project->id)->count();
            WorkItem::where('project_id', $project->id)->delete();
            $counts['milestones_deleted'] = Milestone::where('project_id', $project->id)->count();
            Milestone::where('project_id', $project->id)->delete();
            $counts['roles_deleted'] = Role::where('project_id', $project->id)->count();
            Role::where('project_id', $project->id)->delete();
            $counts['plan_deleted'] = ProjectPlan::where('project_id', $project->id)->exists();
            ProjectPlan::where('project_id', $project->id)->delete();
            $viewIds = DesignView::where('project_id', $project->id)->pluck('id');
            $counts['elements_deleted'] = DesignElement::whereIn('design_view_id', $viewIds)->count();
            DesignElement::whereIn('design_view_id', $viewIds)->delete();
            $counts['views_deleted'] = DesignView::where('project_id', $project->id)->count();
            DesignView::where('project_id', $project->id)->delete();
            $counts['viewpoints_deleted'] = CustomViewpoint::where('project_id', $project->id)->count();
            CustomViewpoint::where('project_id', $project->id)->delete();
            $counts['requirements_deleted'] = Requirement::where('project_id', $project->id)->count();
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

        foreach (($manifest['requirements'] ?? []) as $row) {
            $requirement = $this->applyRequirement($row, $project->id, $effectiveMode, $counts, $drift);
            $slugs['requirements'][$requirement->slug] = $requirement->id;
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

        $plan = $manifest['plan'] ?? null;
        if (is_array($plan)) {
            $this->applyProjectPlan($plan, $project->id, $effectiveMode, $counts, $drift);

            foreach (($plan['roles'] ?? []) as $row) {
                $role = $this->applyRole($row, $project->id, $effectiveMode, $counts, $drift);
                if (! empty($row['slug'])) {
                    $slugs['roles'][$row['slug']] = $role->id;
                }
            }

            foreach (($plan['milestones'] ?? []) as $row) {
                $milestone = $this->applyMilestone($row, $project->id, $effectiveMode, $counts, $drift);
                if (! empty($row['slug'])) {
                    $slugs['milestones'][$row['slug']] = $milestone->id;
                }
            }

            // Two-pass work items: pass 1 creates/updates without parent/dependency
            // links; pass 2 resolves parent + dependencies + requirement/milestone
            // pivots once every work item's slug is in the map.
            $workItemRows = $plan['work_items'] ?? [];
            $workItems = [];
            foreach ($workItemRows as $row) {
                $workItem = $this->applyWorkItem($row, $project->id, $effectiveMode, $slugs, $counts, $drift);
                $workItems[] = ['row' => $row, 'model' => $workItem];
                if (! empty($row['slug'])) {
                    $slugs['work_items'][$row['slug']] = $workItem->id;
                }
            }
            foreach ($workItems as ['row' => $row, 'model' => $workItem]) {
                $this->linkWorkItem($row, $workItem, $project->id, $slugs);
            }
        }

        $verification = $manifest['verification'] ?? [];

        foreach (($verification['plans'] ?? []) as $row) {
            $plan = $this->applyVerificationPlan($row, $project->id, $effectiveMode, $counts, $drift);
            if (! empty($row['slug'])) {
                $slugs['verification_plans'][$row['slug']] = $plan->id;
            }

            foreach (($row['cases'] ?? []) as $caseRow) {
                $case = $this->applyVerificationCase($caseRow, $plan->id, $project->id, $effectiveMode, $slugs, $counts, $drift);
                if (! empty($caseRow['slug'])) {
                    $slugs['verification_cases'][$caseRow['slug']] = $case->id;
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
                $existing->fill($fields);
                if ($existing->isDirty()) {
                    $existing->save();
                    $counts['project_updated'] = true;
                }
            }

            return $existing;
        }

        $project = Project::create($fields + [
            'rigor_level' => $fields['rigor_level'] ?? 2,
            'status' => $fields['status'] ?? 'active',
            'workspace_id' => app(WorkspaceContext::class)->requireId(),
            'created_by_user_id' => $userId,
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

            $existing->fill($fields);
            if ($existing->isDirty()) {
                $existing->save();
                $counts['stakeholders_updated']++;
            }

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

            $existing->fill($fields);
            if ($existing->isDirty()) {
                $existing->save();
                $counts['concerns_updated']++;
            }

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
    private function applyRequirement(array $input, string $projectId, string $mode, array &$counts, array &$drift): Requirement
    {
        $fields = array_intersect_key($input, array_flip([
            'slug', 'doc', 'type', 'text', 'rationale', 'acceptance_criteria', 'source', 'priority', 'tags',
        ]));
        $fields += ['doc' => 'srs', 'type' => 'functional'];

        $existing = Requirement::where('project_id', $projectId)->where('slug', $fields['slug'])->first();

        if ($existing) {
            $this->checkDrift('requirement', $existing->updated_at, $input['_exported_at'] ?? null, $existing->slug, $drift);

            if ($mode === 'fail') {
                $this->failOnCollision('requirement', $existing, $fields);

                return $existing;
            }

            $existing->fill($fields);
            if ($existing->isDirty()) {
                $existing->save();
                $counts['requirements_updated']++;
            }

            return $existing;
        }

        $created = Requirement::create($fields + ['project_id' => $projectId]);
        $counts['requirements_created']++;

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

            $existing->fill($fields);
            if ($existing->isDirty()) {
                $existing->save();
                $counts['viewpoints_updated']++;
            }

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
                $existing->fill($fields);
                if ($existing->isDirty()) {
                    $existing->save();
                    $counts['views_updated']++;
                }
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

            $existing->fill($fields);
            if ($existing->isDirty()) {
                $existing->save();
                $counts['elements_updated']++;
            }

            return $existing;
        }

        $created = DesignElement::create($fields + ['design_view_id' => $viewId]);
        $counts['elements_created']++;

        return $created;
    }

    /**
     * @param  array<string,mixed>  $input
     * @param  array<string,mixed>  $counts
     * @param  array<int,array<string,mixed>>  $drift
     */
    private function applyProjectPlan(array $input, string $projectId, string $mode, array &$counts, array &$drift): ProjectPlan
    {
        $fields = array_intersect_key($input, array_flip([
            'status', 'scope_summary', 'objectives', 'deliverables_summary',
            'approach', 'organization_summary', 'assumptions', 'constraints',
            'budget_summary',
        ]));

        $existing = ProjectPlan::where('project_id', $projectId)->first();

        if ($existing) {
            $this->checkDrift('plan', $existing->updated_at, $input['_exported_at'] ?? null, $existing->id, $drift);

            if ($mode === 'fail') {
                $this->failOnCollision('plan', $existing, $fields);

                return $existing;
            }

            $existing->fill($fields);
            if ($existing->isDirty()) {
                $existing->save();
                $counts['plan_updated'] = true;
            }

            return $existing;
        }

        $plan = ProjectPlan::create($fields + ['project_id' => $projectId]);
        $counts['plan_created'] = true;

        return $plan;
    }

    /**
     * @param  array<string,mixed>  $input
     * @param  array<string,mixed>  $counts
     * @param  array<int,array<string,mixed>>  $drift
     */
    private function applyRole(array $input, string $projectId, string $mode, array &$counts, array &$drift): Role
    {
        $fields = array_intersect_key($input, array_flip([
            'name', 'responsibilities', 'weekly_capacity_hours',
            'hourly_rate_amount', 'rate_currency',
        ]));

        $existing = Role::where('project_id', $projectId)->where('name', $fields['name'])->first();

        if ($existing) {
            $this->checkDrift('role', $existing->updated_at, $input['_exported_at'] ?? null, $existing->name, $drift);

            if ($mode === 'fail') {
                $this->failOnCollision('role', $existing, $fields);

                return $existing;
            }

            $existing->fill($fields);
            if ($existing->isDirty()) {
                $existing->save();
                $counts['roles_updated']++;
            }

            return $existing;
        }

        $created = Role::create($fields + ['project_id' => $projectId]);
        $counts['roles_created']++;

        return $created;
    }

    /**
     * @param  array<string,mixed>  $input
     * @param  array<string,mixed>  $counts
     * @param  array<int,array<string,mixed>>  $drift
     */
    private function applyMilestone(array $input, string $projectId, string $mode, array &$counts, array &$drift): Milestone
    {
        $fields = array_intersect_key($input, array_flip([
            'name', 'target_date', 'exit_criteria', 'status',
        ]));

        $existing = Milestone::where('project_id', $projectId)->where('name', $fields['name'])->first();

        if ($existing) {
            $this->checkDrift('milestone', $existing->updated_at, $input['_exported_at'] ?? null, $existing->name, $drift);

            if ($mode === 'fail') {
                $this->failOnCollisionMilestone($existing, $fields);

                return $existing;
            }

            $existing->fill($fields);
            if ($existing->isDirty()) {
                $existing->save();
                $counts['milestones_updated']++;
            }

            return $existing;
        }

        $created = Milestone::create($fields + ['project_id' => $projectId]);
        $counts['milestones_created']++;

        return $created;
    }

    /**
     * @param  array<string,mixed>  $input
     * @param  array<string,array<string,string>>  $slugs
     * @param  array<string,mixed>  $counts
     * @param  array<int,array<string,mixed>>  $drift
     */
    private function applyWorkItem(array $input, string $projectId, string $mode, array $slugs, array &$counts, array &$drift): WorkItem
    {
        $fields = array_intersect_key($input, array_flip([
            'kind', 'name', 'description', 'status',
            'planned_start_date', 'due_date',
            'effort_estimate', 'effort_actual',
            'effort_estimate_hours', 'effort_actual_hours',
            'cost_estimate_amount', 'cost_actual_amount', 'cost_currency',
        ]));

        if (isset($input['responsible_role'])) {
            $ref = $input['responsible_role'];
            $roleId = $slugs['roles'][$ref]
                ?? Role::where('project_id', $projectId)->where('name', $ref)->value('id');

            if ($roleId === null) {
                throw new RuntimeException("Work item [{$input['name']}] references unknown role [{$ref}].");
            }
            $fields['responsible_role_id'] = $roleId;
        }

        $existing = WorkItem::where('project_id', $projectId)->where('name', $fields['name'])->first();

        if ($existing) {
            $this->checkDrift('work_item', $existing->updated_at, $input['_exported_at'] ?? null, $existing->name, $drift);

            if ($mode === 'fail') {
                $this->failOnCollision('work_item', $existing, $fields);

                return $existing;
            }

            $existing->fill($fields);
            if ($existing->isDirty()) {
                $existing->save();
                $counts['work_items_updated']++;
            }

            return $existing;
        }

        $created = WorkItem::create($fields + ['project_id' => $projectId]);
        $counts['work_items_created']++;

        return $created;
    }

    /**
     * Pass 2 of work item application: resolves parent slug, dependencies,
     * and pivot links to requirements + milestones once every work item in
     * the manifest has an ULID.
     *
     * @param  array<string,mixed>  $input
     * @param  array<string,array<string,string>>  $slugs
     */
    private function linkWorkItem(array $input, WorkItem $workItem, string $projectId, array $slugs): void
    {
        $dirty = false;

        if (array_key_exists('parent', $input)) {
            if ($input['parent'] === null) {
                $workItem->parent_id = null;
                $dirty = true;
            } else {
                $ref = $input['parent'];
                $parentId = $slugs['work_items'][$ref]
                    ?? WorkItem::where('project_id', $projectId)->where('name', $ref)->value('id');

                if ($parentId === null) {
                    throw new RuntimeException("Work item [{$workItem->name}] references unknown parent [{$ref}].");
                }
                if ($parentId === $workItem->id) {
                    throw new RuntimeException("Work item [{$workItem->name}] cannot be its own parent.");
                }
                $workItem->parent_id = $parentId;
                $dirty = true;
            }
        }

        if ($dirty) {
            $workItem->save();
        }

        if (array_key_exists('requirements', $input)) {
            $ids = [];
            foreach ((array) $input['requirements'] as $ref) {
                $ids[] = $slugs['requirements'][$ref]
                    ?? Requirement::where('project_id', $projectId)->where('slug', $ref)->value('id')
                    ?? throw new RuntimeException("Work item [{$workItem->name}] references unknown requirement [{$ref}].");
            }
            $workItem->requirements()->sync($ids);
        }

        if (array_key_exists('milestones', $input)) {
            $ids = [];
            foreach ((array) $input['milestones'] as $ref) {
                $ids[] = $slugs['milestones'][$ref]
                    ?? Milestone::where('project_id', $projectId)->where('name', $ref)->value('id')
                    ?? throw new RuntimeException("Work item [{$workItem->name}] references unknown milestone [{$ref}].");
            }
            $workItem->milestones()->sync($ids);
        }

        if (array_key_exists('dependencies', $input)) {
            $deps = [];
            foreach ((array) $input['dependencies'] as $depInput) {
                $depRef = is_array($depInput) ? ($depInput['work_item'] ?? null) : $depInput;
                $depKind = is_array($depInput) ? ($depInput['kind'] ?? 'finish_to_start') : 'finish_to_start';

                if ($depRef === null) {
                    throw new RuntimeException("Work item [{$workItem->name}] has a dependency entry missing `work_item`.");
                }
                $depId = $slugs['work_items'][$depRef]
                    ?? WorkItem::where('project_id', $projectId)->where('name', $depRef)->value('id')
                    ?? throw new RuntimeException("Work item [{$workItem->name}] references unknown dependency [{$depRef}].");

                $deps[$depId] = ['kind' => $depKind];
            }
            $workItem->dependencies()->sync($deps);
        }
    }

    /**
     * @param  array<string,mixed>  $input
     * @param  array<string,mixed>  $counts
     * @param  array<int,array<string,mixed>>  $drift
     */
    private function applyVerificationPlan(array $input, string $projectId, string $mode, array &$counts, array &$drift): TestPlan
    {
        $fields = array_intersect_key($input, array_flip([
            'level', 'name', 'scope', 'approach', 'pass_fail_criteria',
        ]));

        $existing = TestPlan::where('project_id', $projectId)->where('name', $fields['name'])->first();

        if ($existing) {
            $this->checkDrift('verification_plan', $existing->updated_at, $input['_exported_at'] ?? null, $existing->name, $drift);

            if ($mode === 'fail') {
                $this->failOnCollision('verification_plan', $existing, $fields);

                return $existing;
            }

            $existing->fill($fields);
            if ($existing->isDirty()) {
                $existing->save();
                $counts['verification_plans_updated']++;
            }

            return $existing;
        }

        $created = TestPlan::create($fields + ['project_id' => $projectId]);
        $counts['verification_plans_created']++;

        return $created;
    }

    /**
     * @param  array<string,mixed>  $input
     * @param  array<string,array<string,string>>  $slugs
     * @param  array<string,mixed>  $counts
     * @param  array<int,array<string,mixed>>  $drift
     */
    private function applyVerificationCase(array $input, string $planId, string $projectId, string $mode, array $slugs, array &$counts, array &$drift): TestCase
    {
        $fields = array_intersect_key($input, array_flip([
            'name', 'objective', 'preconditions', 'inputs',
            'expected_results', 'environment',
        ]));

        $requirementIds = null;
        if (array_key_exists('verifies_requirements', $input)) {
            $requirementIds = [];
            foreach ((array) $input['verifies_requirements'] as $ref) {
                $requirementIds[] = $slugs['requirements'][$ref]
                    ?? Requirement::where('project_id', $projectId)->where('slug', $ref)->value('id')
                    ?? throw new RuntimeException("Verification case [{$input['name']}] references unknown requirement [{$ref}].");
            }
        }

        $existing = TestCase::where('test_plan_id', $planId)->where('name', $fields['name'])->first();

        if ($existing) {
            $this->checkDrift('verification_case', $existing->updated_at, $input['_exported_at'] ?? null, $existing->name, $drift);

            if ($mode === 'fail') {
                $this->failOnCollision('verification_case', $existing, $fields);
            } else {
                $existing->fill($fields);
                if ($existing->isDirty()) {
                    $existing->save();
                    $counts['verification_cases_updated']++;
                }
            }

            if (is_array($requirementIds)) {
                $existing->requirements()->sync($requirementIds);
            }

            return $existing;
        }

        $created = TestCase::create($fields + ['test_plan_id' => $planId]);
        $counts['verification_cases_created']++;

        if (is_array($requirementIds)) {
            $created->requirements()->sync($requirementIds);
        }

        return $created;
    }

    /**
     * Milestone fail-mode comparison normalizes `target_date` (Carbon ↔ string)
     * before delegating to the generic collision check.
     *
     * @param  array<string,mixed>  $fields
     */
    private function failOnCollisionMilestone(Milestone $existing, array $fields): void
    {
        if (isset($fields['target_date']) && is_string($fields['target_date'])) {
            $existingDate = $existing->target_date?->toDateString();
            $providedDate = Carbon::parse($fields['target_date'])->toDateString();
            if ($existingDate !== $providedDate) {
                throw new RuntimeException(
                    "milestone [{$existing->name}] already exists with different content; fail mode aborts on any difference. Use merge or replace mode to update."
                );
            }
            unset($fields['target_date']);
        }
        $this->failOnCollision('milestone', $existing, $fields);
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
