<?php

namespace App\Growth\Manifest;

use App\Growth\Logging\LogLevel;
use App\Growth\Logging\LogReporter;
use App\Growth\Logging\NullLogReporter;
use App\Growth\Progress\NullProgressReporter;
use App\Growth\Progress\ProgressReporter;
use App\Models\Project;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Produces a manifest array describing a Growth project's full structure
 * (project + stakeholders + concerns + requirements + architecture + plan +
 * verification). Output ordering is deterministic (alphabetical by slug
 * within each list) so two exports of the same project produce byte-identical
 * JSON, and re-applying an unchanged export is a no-op.
 *
 * Each emitted entity carries an `_exported_at` equal to its `updated_at`, so
 * the applier's drift check correctly reports rows the DB has touched since.
 */
class ManifestExporter
{
    public const SECTIONS = [
        'stakeholders',
        'concerns',
        'requirements',
        'architecture',
        'plan',
        'verification',
    ];

    /**
     * Export a project manifest, optionally restricted to a subset of sections.
     *
     * - `$sections === null` or `['*']` exports the full manifest (round-trip
     *   shape, suitable for re-apply).
     * - An array of section names emits only `project` plus the requested
     *   sections. Unknown section names throw {@see InvalidArgumentException}.
     *
     * The `project` section is always present.
     *
     * @param  list<string>|null  $sections
     * @return array<string,mixed>
     */
    public function export(string $projectId, ?array $sections = null, ProgressReporter $progress = new NullProgressReporter, LogReporter $log = new NullLogReporter): array
    {
        $wanted = $this->resolveSections($sections);

        $project = Project::query()
            ->with($this->eagerLoadsFor($wanted))
            ->findOrFail($projectId);

        $total = count($wanted) + 1;
        $step = 0;

        $stakeholderSlugs = isset($wanted['stakeholders']) || isset($wanted['concerns'])
            ? $this->assignSlugs(
                $project->stakeholders->sortBy(fn ($s) => Str::slug($s->name))->values(),
                fn ($s) => $s->name,
            )
            : [];
        $concernSlugs = isset($wanted['concerns']) || isset($wanted['architecture'])
            ? $this->assignSlugs(
                $project->concerns->sortBy(fn ($c) => Str::slug($c->text))->values(),
                fn ($c) => $c->text,
            )
            : [];
        $requirementSlugs = isset($wanted['requirements']) || isset($wanted['plan']) || isset($wanted['verification'])
            ? $project->requirements->pluck('slug', 'id')->all()
            : [];
        $viewpointSlugs = isset($wanted['architecture'])
            ? $this->assignSlugs(
                $project->customViewpoints->sortBy(fn ($v) => Str::slug($v->name))->values(),
                fn ($v) => $v->name,
            )
            : [];
        $viewSlugs = isset($wanted['architecture'])
            ? $this->assignSlugs(
                $project->designViews->sortBy(fn ($v) => Str::slug($v->name))->values(),
                fn ($v) => $v->name,
            )
            : [];
        $roleSlugs = isset($wanted['plan'])
            ? $this->assignSlugs(
                $project->roles->sortBy(fn ($r) => Str::slug($r->name))->values(),
                fn ($r) => $r->name,
            )
            : [];
        $milestoneSlugs = isset($wanted['plan'])
            ? $this->assignSlugs(
                $project->milestones->sortBy(fn ($m) => Str::slug($m->name))->values(),
                fn ($m) => $m->name,
            )
            : [];
        $workItemSlugs = isset($wanted['plan'])
            ? $this->assignSlugs(
                $project->workItems->sortBy(fn ($w) => Str::slug($w->name))->values(),
                fn ($w) => $w->name,
            )
            : [];
        $testPlanSlugs = isset($wanted['verification'])
            ? $this->assignSlugs(
                $project->testPlans->sortBy(fn ($p) => Str::slug($p->name))->values(),
                fn ($p) => $p->name,
            )
            : [];

        $manifest = [
            'project' => $this->emitProject($project),
        ];
        $progress->report(++$step, $total, 'Exported project');
        $log->log(LogLevel::Info, 'Exported project', ['project' => $project->name]);

        if (isset($wanted['stakeholders'])) {
            $stakeholders = $project->stakeholders
                ->sortBy(fn ($s) => $stakeholderSlugs[$s->id])
                ->map(fn ($s) => $this->emitStakeholder($s, $stakeholderSlugs))
                ->values()
                ->all();
            if ($stakeholders) {
                $manifest['stakeholders'] = $stakeholders;
            }
            $progress->report(++$step, $total, 'Exported stakeholders');
            $log->log(LogLevel::Info, 'Exported stakeholders', ['count' => count($stakeholders)]);
        }

        if (isset($wanted['concerns'])) {
            $concerns = $project->concerns
                ->sortBy(fn ($c) => $concernSlugs[$c->id])
                ->map(fn ($c) => $this->emitConcern($c, $concernSlugs, $stakeholderSlugs))
                ->values()
                ->all();
            if ($concerns) {
                $manifest['concerns'] = $concerns;
            }
            $progress->report(++$step, $total, 'Exported concerns');
            $log->log(LogLevel::Info, 'Exported concerns', ['count' => count($concerns)]);
        }

        if (isset($wanted['requirements'])) {
            $requirements = $project->requirements
                ->sortBy('slug')
                ->map(fn ($r) => $this->emitRequirement($r))
                ->values()
                ->all();
            if ($requirements) {
                $manifest['requirements'] = $requirements;
            }
            $progress->report(++$step, $total, 'Exported requirements');
            $log->log(LogLevel::Info, 'Exported requirements', ['count' => count($requirements)]);
        }

        if (isset($wanted['architecture'])) {
            $architecture = [];
            $viewpoints = $project->customViewpoints
                ->sortBy(fn ($v) => $viewpointSlugs[$v->id])
                ->map(fn ($v) => $this->emitViewpoint($v, $viewpointSlugs))
                ->values()
                ->all();
            if ($viewpoints) {
                $architecture['viewpoints'] = $viewpoints;
            }
            $views = $project->designViews
                ->sortBy(fn ($v) => $viewSlugs[$v->id])
                ->map(fn ($v) => $this->emitView($v, $viewSlugs, $concernSlugs))
                ->values()
                ->all();
            if ($views) {
                $architecture['views'] = $views;
            }
            if ($architecture) {
                $manifest['architecture'] = $architecture;
            }
            $progress->report(++$step, $total, 'Exported architecture');
            $log->log(LogLevel::Info, 'Exported architecture', [
                'viewpoints' => count($viewpoints),
                'views' => count($views),
            ]);
        }

        if (isset($wanted['plan'])) {
            if ($project->projectPlan) {
                $manifest['plan'] = $this->emitPlan(
                    $project,
                    $roleSlugs,
                    $milestoneSlugs,
                    $workItemSlugs,
                    $requirementSlugs,
                );
            }
            $progress->report(++$step, $total, 'Exported plan');
            $log->log(LogLevel::Info, 'Exported plan', ['present' => $project->projectPlan !== null]);
        }

        if (isset($wanted['verification'])) {
            $verification = [];
            $plans = $project->testPlans
                ->sortBy(fn ($p) => $testPlanSlugs[$p->id])
                ->map(fn ($p) => $this->emitVerificationPlan($p, $testPlanSlugs, $requirementSlugs))
                ->values()
                ->all();
            if ($plans) {
                $verification['plans'] = $plans;
                $manifest['verification'] = $verification;
            }
            $progress->report(++$step, $total, 'Exported verification');
            $log->log(LogLevel::Info, 'Exported verification', ['plans' => count($plans)]);
        }

        return $manifest;
    }

    /**
     * Cheap snapshot of what a project contains: section names with row counts.
     * Used by the manifest TOC resource and the tool's bounded default response
     * so callers can decide which slices to fetch in full.
     *
     * @return array<string,mixed>
     */
    public function tableOfContents(string $projectId): array
    {
        $project = Project::query()
            ->withCount([
                'stakeholders',
                'concerns',
                'requirements',
                'customViewpoints',
                'designViews',
                'roles',
                'milestones',
                'workItems',
                'testPlans',
            ])
            ->with(['projectPlan:id,project_id'])
            ->findOrFail($projectId);

        $verificationCases = $project->testPlans()
            ->withCount('cases')
            ->get(['id'])
            ->sum('cases_count');

        return [
            'project' => [
                'id' => $project->id,
                'name' => $project->name,
                'description' => $project->description,
                'rigor_level' => $project->rigor_level,
                'status' => $project->status,
                '_exported_at' => $project->updated_at?->toIso8601String(),
            ],
            'sections' => [
                'stakeholders' => ['count' => (int) $project->stakeholders_count],
                'concerns' => ['count' => (int) $project->concerns_count],
                'requirements' => ['count' => (int) $project->requirements_count],
                'architecture' => [
                    'viewpoint_count' => (int) $project->custom_viewpoints_count,
                    'view_count' => (int) $project->design_views_count,
                ],
                'plan' => [
                    'present' => $project->projectPlan !== null,
                    'role_count' => (int) $project->roles_count,
                    'milestone_count' => (int) $project->milestones_count,
                    'work_item_count' => (int) $project->work_items_count,
                ],
                'verification' => [
                    'plan_count' => (int) $project->test_plans_count,
                    'case_count' => (int) $verificationCases,
                ],
            ],
            'sections_available' => self::SECTIONS,
            'resource_uris' => [
                'toc' => "growth://projects/{$project->id}/manifest",
                'sections' => array_combine(
                    self::SECTIONS,
                    array_map(fn (string $s) => "growth://projects/{$project->id}/manifest/{$s}", self::SECTIONS),
                ),
            ],
        ];
    }

    /**
     * Normalise the caller's section selection into a lookup map keyed by
     * section name. `null` or `['*']` means every section; an empty array
     * means no sections (project header only).
     *
     * @param  list<string>|null  $sections
     * @return array<string,true>
     */
    private function resolveSections(?array $sections): array
    {
        if ($sections === null || $sections === ['*']) {
            return array_fill_keys(self::SECTIONS, true);
        }

        $unknown = array_values(array_diff($sections, self::SECTIONS));
        if ($unknown !== []) {
            throw new InvalidArgumentException(
                'Unknown manifest section(s): '.implode(', ', $unknown).'. Available: '.implode(', ', self::SECTIONS).'.'
            );
        }

        return array_fill_keys($sections, true);
    }

    /**
     * @param  array<string,true>  $wanted
     * @return list<string>
     */
    private function eagerLoadsFor(array $wanted): array
    {
        $loads = [];
        if (isset($wanted['stakeholders']) || isset($wanted['concerns'])) {
            $loads[] = 'stakeholders';
        }
        if (isset($wanted['concerns']) || isset($wanted['architecture'])) {
            // Architecture views serialise `addresses_concerns` by concern slug,
            // so concern rows must be loaded even when the caller didn't ask
            // for the `concerns` section directly.
            $loads[] = 'concerns';
        }
        if (isset($wanted['requirements']) || isset($wanted['plan']) || isset($wanted['verification'])) {
            $loads[] = 'requirements';
        }
        if (isset($wanted['architecture'])) {
            $loads[] = 'customViewpoints';
            $loads[] = 'designViews.elements';
            $loads[] = 'designViews.concerns';
        }
        if (isset($wanted['plan'])) {
            $loads[] = 'projectPlan';
            $loads[] = 'roles';
            $loads[] = 'milestones';
            $loads[] = 'workItems.responsibleRole';
            $loads[] = 'workItems.parent';
            $loads[] = 'workItems.requirements';
            $loads[] = 'workItems.milestones';
            $loads[] = 'workItems.dependencies';
        }
        if (isset($wanted['verification'])) {
            $loads[] = 'testPlans.cases.requirements';
        }

        return $loads;
    }

    /**
     * @param  Collection<int,Model>  $models
     * @param  callable(Model):string  $nameFn
     * @return array<string,string> Map of model ID → assigned slug.
     */
    private function assignSlugs($models, callable $nameFn): array
    {
        $slugs = [];
        $used = [];
        foreach ($models as $model) {
            $base = Str::slug($nameFn($model)) ?: 'item';
            $slug = $base;
            $n = 2;
            while (isset($used[$slug])) {
                $slug = $base.'-'.$n;
                $n++;
            }
            $used[$slug] = true;
            $slugs[$model->id] = $slug;
        }

        return $slugs;
    }

    /**
     * @return array<string,mixed>
     */
    private function emitProject(Project $project): array
    {
        return [
            'id' => $project->id,
            'name' => $project->name,
            'description' => $project->description,
            'rigor_level' => $project->rigor_level,
            'status' => $project->status,
            '_exported_at' => $project->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @param  array<string,string>  $stakeholderSlugs
     * @return array<string,mixed>
     */
    private function emitStakeholder($stakeholder, array $stakeholderSlugs): array
    {
        return $this->compact([
            'slug' => $stakeholderSlugs[$stakeholder->id],
            'name' => $stakeholder->name,
            'role' => $stakeholder->role,
            'kind' => $stakeholder->kind,
            'description' => $stakeholder->description,
            '_exported_at' => $stakeholder->updated_at?->toIso8601String(),
        ]);
    }

    /**
     * @param  array<string,string>  $concernSlugs
     * @param  array<string,string>  $stakeholderSlugs
     * @return array<string,mixed>
     */
    private function emitConcern($concern, array $concernSlugs, array $stakeholderSlugs): array
    {
        $raisedBy = $concern->raised_by_stakeholder_id
            ? ($stakeholderSlugs[$concern->raised_by_stakeholder_id] ?? null)
            : null;

        return $this->compact([
            'slug' => $concernSlugs[$concern->id],
            'text' => $concern->text,
            'raised_by' => $raisedBy,
            'viewpoint_hints' => $concern->viewpoint_hints,
            '_exported_at' => $concern->updated_at?->toIso8601String(),
        ]);
    }

    /**
     * @return array<string,mixed>
     */
    private function emitRequirement($requirement): array
    {
        return $this->compact([
            'slug' => $requirement->slug,
            'doc' => $requirement->doc,
            'type' => $requirement->type,
            'text' => $requirement->text,
            'rationale' => $requirement->rationale,
            'acceptance_criteria' => $requirement->acceptance_criteria,
            'source' => $requirement->source,
            'priority' => $requirement->priority,
            'tags' => $requirement->tags,
            // Emit only when set — `compact()` keeps `false`, so a plain
            // boolean would noise every non-UI requirement with `false`.
            'renders_ui' => $requirement->renders_ui ?: null,
            '_exported_at' => $requirement->updated_at?->toIso8601String(),
        ]);
    }

    /**
     * @param  array<string,string>  $viewpointSlugs
     * @return array<string,mixed>
     */
    private function emitViewpoint($viewpoint, array $viewpointSlugs): array
    {
        return $this->compact([
            'slug' => $viewpointSlugs[$viewpoint->id],
            'name' => $viewpoint->name,
            'concerns' => $viewpoint->concerns,
            'element_types' => $viewpoint->element_types,
            'languages' => $viewpoint->languages,
            'source' => $viewpoint->source,
            '_exported_at' => $viewpoint->updated_at?->toIso8601String(),
        ]);
    }

    /**
     * @param  array<string,string>  $viewSlugs
     * @param  array<string,string>  $concernSlugs
     * @return array<string,mixed>
     */
    private function emitView($view, array $viewSlugs, array $concernSlugs): array
    {
        $addressesConcerns = $view->concerns
            ->map(fn ($c) => $concernSlugs[$c->id])
            ->sort()
            ->values()
            ->all();

        $elements = $view->elements
            ->sortBy(fn ($e) => Str::slug($e->name))
            ->map(fn ($e) => $this->emitElement($e))
            ->values()
            ->all();

        return $this->compact([
            'slug' => $viewSlugs[$view->id],
            'name' => $view->name,
            'viewpoint' => $view->viewpoint,
            'description' => $view->description,
            'addresses_concerns' => $addressesConcerns ?: null,
            'elements' => $elements ?: null,
            '_exported_at' => $view->updated_at?->toIso8601String(),
        ]);
    }

    /**
     * @return array<string,mixed>
     */
    private function emitElement($element): array
    {
        return $this->compact([
            'slug' => Str::slug($element->name) ?: 'element',
            'kind' => $element->kind,
            'name' => $element->name,
            'type' => $element->type,
            'purpose' => $element->purpose,
            'properties' => $element->properties,
            '_exported_at' => $element->updated_at?->toIso8601String(),
        ]);
    }

    /**
     * @param  array<string,string>  $roleSlugs
     * @param  array<string,string>  $milestoneSlugs
     * @param  array<string,string>  $workItemSlugs
     * @param  array<string,string>  $requirementSlugs
     * @return array<string,mixed>
     */
    private function emitPlan(
        Project $project,
        array $roleSlugs,
        array $milestoneSlugs,
        array $workItemSlugs,
        array $requirementSlugs,
    ): array {
        $plan = $project->projectPlan;

        $out = $this->compact([
            'status' => $plan->status,
            'scope_summary' => $plan->scope_summary,
            'objectives' => $plan->objectives,
            'deliverables_summary' => $plan->deliverables_summary,
            'approach' => $plan->approach,
            'organization_summary' => $plan->organization_summary,
            'assumptions' => $plan->assumptions,
            'constraints' => $plan->constraints,
            'budget_summary' => $plan->budget_summary,
            '_exported_at' => $plan->updated_at?->toIso8601String(),
        ]);

        $roles = $project->roles
            ->sortBy(fn ($r) => $roleSlugs[$r->id])
            ->map(fn ($r) => $this->compact([
                'slug' => $roleSlugs[$r->id],
                'name' => $r->name,
                'responsibilities' => $r->responsibilities,
                '_exported_at' => $r->updated_at?->toIso8601String(),
            ]))
            ->values()
            ->all();
        if ($roles) {
            $out['roles'] = $roles;
        }

        $milestones = $project->milestones
            ->sortBy(fn ($m) => $milestoneSlugs[$m->id])
            ->map(fn ($m) => $this->compact([
                'slug' => $milestoneSlugs[$m->id],
                'name' => $m->name,
                'exit_criteria' => $m->exit_criteria,
                'status' => $m->status,
                '_exported_at' => $m->updated_at?->toIso8601String(),
            ]))
            ->values()
            ->all();
        if ($milestones) {
            $out['milestones'] = $milestones;
        }

        $workItems = $project->workItems
            ->sortBy(fn ($w) => $workItemSlugs[$w->id])
            ->map(fn ($w) => $this->emitWorkItem($w, $workItemSlugs, $roleSlugs, $milestoneSlugs, $requirementSlugs))
            ->values()
            ->all();
        if ($workItems) {
            $out['work_items'] = $workItems;
        }

        return $out;
    }

    /**
     * @param  array<string,string>  $workItemSlugs
     * @param  array<string,string>  $roleSlugs
     * @param  array<string,string>  $milestoneSlugs
     * @param  array<string,string>  $requirementSlugs
     * @return array<string,mixed>
     */
    private function emitWorkItem(
        $workItem,
        array $workItemSlugs,
        array $roleSlugs,
        array $milestoneSlugs,
        array $requirementSlugs,
    ): array {
        $requirements = $workItem->requirements
            ->map(fn ($r) => $requirementSlugs[$r->id])
            ->sort()
            ->values()
            ->all();

        $milestones = $workItem->milestones
            ->map(fn ($m) => $milestoneSlugs[$m->id])
            ->sort()
            ->values()
            ->all();

        $dependencies = $workItem->dependencies
            ->map(fn ($d) => $workItemSlugs[$d->id])
            ->sort()
            ->values()
            ->all();

        return $this->compact([
            'slug' => $workItemSlugs[$workItem->id],
            'kind' => $workItem->kind,
            'name' => $workItem->name,
            'description' => $workItem->description,
            'status' => $workItem->status,
            'responsible_role' => $workItem->responsible_role_id
                ? ($roleSlugs[$workItem->responsible_role_id] ?? null)
                : null,
            'parent' => $workItem->parent_id
                ? ($workItemSlugs[$workItem->parent_id] ?? null)
                : null,
            'requirements' => $requirements ?: null,
            'milestones' => $milestones ?: null,
            'dependencies' => $dependencies ?: null,
            '_exported_at' => $workItem->updated_at?->toIso8601String(),
        ]);
    }

    /**
     * @param  array<string,string>  $testPlanSlugs
     * @param  array<string,string>  $requirementSlugs
     * @return array<string,mixed>
     */
    private function emitVerificationPlan($plan, array $testPlanSlugs, array $requirementSlugs): array
    {
        $caseSlugs = $this->assignSlugs(
            $plan->cases->sortBy(fn ($c) => Str::slug($c->name))->values(),
            fn ($c) => $c->name,
        );

        $cases = $plan->cases
            ->sortBy(fn ($c) => $caseSlugs[$c->id])
            ->map(fn ($c) => $this->emitVerificationCase($c, $caseSlugs, $requirementSlugs))
            ->values()
            ->all();

        return $this->compact([
            'slug' => $testPlanSlugs[$plan->id],
            'level' => $plan->level,
            'name' => $plan->name,
            'scope' => $plan->scope,
            'approach' => $plan->approach,
            'pass_fail_criteria' => $plan->pass_fail_criteria,
            'cases' => $cases ?: null,
            '_exported_at' => $plan->updated_at?->toIso8601String(),
        ]);
    }

    /**
     * @param  array<string,string>  $caseSlugs
     * @param  array<string,string>  $requirementSlugs
     * @return array<string,mixed>
     */
    private function emitVerificationCase($case, array $caseSlugs, array $requirementSlugs): array
    {
        $requirements = $case->requirements
            ->map(fn ($r) => $requirementSlugs[$r->id])
            ->sort()
            ->values()
            ->all();

        return $this->compact([
            'slug' => $caseSlugs[$case->id],
            'name' => $case->name,
            'objective' => $case->objective,
            'preconditions' => $case->preconditions,
            'inputs' => $case->inputs,
            'expected_results' => $case->expected_results,
            'environment' => $case->environment,
            'verifies_requirements' => $requirements ?: null,
            '_exported_at' => $case->updated_at?->toIso8601String(),
        ]);
    }

    /**
     * @param  array<string,mixed>  $row
     * @return array<string,mixed>
     */
    private function compact(array $row): array
    {
        return array_filter($row, fn ($v) => $v !== null && $v !== [] && $v !== '');
    }
}
