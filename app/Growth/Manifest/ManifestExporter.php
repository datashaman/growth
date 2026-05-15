<?php

namespace App\Growth\Manifest;

use App\Models\Project;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

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
    /**
     * @return array<string,mixed>
     */
    public function export(string $projectId): array
    {
        $project = Project::query()
            ->with([
                'stakeholders',
                'concerns',
                'requirements',
                'customViewpoints',
                'designViews.elements',
                'designViews.concerns',
                'projectPlan',
                'roles',
                'milestones',
                'workItems.responsibleRole',
                'workItems.parent',
                'workItems.requirements',
                'workItems.milestones',
                'workItems.dependencies',
                'testPlans.cases.requirements',
            ])
            ->findOrFail($projectId);

        $stakeholderSlugs = $this->assignSlugs(
            $project->stakeholders->sortBy(fn ($s) => Str::slug($s->name))->values(),
            fn ($s) => $s->name,
        );
        $concernSlugs = $this->assignSlugs(
            $project->concerns->sortBy(fn ($c) => Str::slug($c->text))->values(),
            fn ($c) => $c->text,
        );
        $requirementSlugs = $project->requirements->pluck('slug', 'id')->all();
        $viewpointSlugs = $this->assignSlugs(
            $project->customViewpoints->sortBy(fn ($v) => Str::slug($v->name))->values(),
            fn ($v) => $v->name,
        );
        $viewSlugs = $this->assignSlugs(
            $project->designViews->sortBy(fn ($v) => Str::slug($v->name))->values(),
            fn ($v) => $v->name,
        );
        $roleSlugs = $this->assignSlugs(
            $project->roles->sortBy(fn ($r) => Str::slug($r->name))->values(),
            fn ($r) => $r->name,
        );
        $milestoneSlugs = $this->assignSlugs(
            $project->milestones->sortBy(fn ($m) => Str::slug($m->name))->values(),
            fn ($m) => $m->name,
        );
        $workItemSlugs = $this->assignSlugs(
            $project->workItems->sortBy(fn ($w) => Str::slug($w->name))->values(),
            fn ($w) => $w->name,
        );
        $testPlanSlugs = $this->assignSlugs(
            $project->testPlans->sortBy(fn ($p) => Str::slug($p->name))->values(),
            fn ($p) => $p->name,
        );

        $manifest = [
            'project' => $this->emitProject($project),
        ];

        $stakeholders = $project->stakeholders
            ->sortBy(fn ($s) => $stakeholderSlugs[$s->id])
            ->map(fn ($s) => $this->emitStakeholder($s, $stakeholderSlugs))
            ->values()
            ->all();
        if ($stakeholders) {
            $manifest['stakeholders'] = $stakeholders;
        }

        $concerns = $project->concerns
            ->sortBy(fn ($c) => $concernSlugs[$c->id])
            ->map(fn ($c) => $this->emitConcern($c, $concernSlugs, $stakeholderSlugs))
            ->values()
            ->all();
        if ($concerns) {
            $manifest['concerns'] = $concerns;
        }

        $requirements = $project->requirements
            ->sortBy('slug')
            ->map(fn ($r) => $this->emitRequirement($r))
            ->values()
            ->all();
        if ($requirements) {
            $manifest['requirements'] = $requirements;
        }

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

        if ($project->projectPlan) {
            $manifest['plan'] = $this->emitPlan(
                $project,
                $roleSlugs,
                $milestoneSlugs,
                $workItemSlugs,
                $requirementSlugs,
            );
        }

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

        return $manifest;
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
                'weekly_capacity_hours' => $r->weekly_capacity_hours,
                'hourly_rate_amount' => $r->hourly_rate_amount,
                'rate_currency' => $r->rate_currency,
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
                'target_date' => $m->target_date?->toDateString(),
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
            ->map(fn ($d) => [
                'work_item' => $workItemSlugs[$d->id],
                'kind' => $d->pivot->kind,
            ])
            ->sortBy('work_item')
            ->values()
            ->all();

        return $this->compact([
            'slug' => $workItemSlugs[$workItem->id],
            'kind' => $workItem->kind,
            'name' => $workItem->name,
            'description' => $workItem->description,
            'status' => $workItem->status,
            'planned_start_date' => $workItem->planned_start_date?->toDateString(),
            'due_date' => $workItem->due_date?->toDateString(),
            'effort_estimate' => $workItem->effort_estimate,
            'effort_actual' => $workItem->effort_actual,
            'effort_estimate_hours' => $workItem->effort_estimate_hours,
            'effort_actual_hours' => $workItem->effort_actual_hours,
            'cost_estimate_amount' => $workItem->cost_estimate_amount,
            'cost_actual_amount' => $workItem->cost_actual_amount,
            'cost_currency' => $workItem->cost_currency,
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
