<?php

namespace App\Mcp\Resources\Project;

use App\Mcp\Resources\Support\CitationRenderer;
use App\Models\Project;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\MimeType;
use Laravel\Mcp\Server\Contracts\HasUriTemplate;
use Laravel\Mcp\Server\Resource;
use Laravel\Mcp\Support\UriTemplate;

#[Description('Delivery plan assembled from ProjectPlan, milestones, roles, and the work-item tree. Renders citations inline.')]
#[MimeType('text/markdown')]
class ProjectPmpResource extends Resource implements HasUriTemplate
{
    public function uriTemplate(): UriTemplate
    {
        return new UriTemplate('growth://projects/{project}/pmp');
    }

    public function handle(Request $request): Response
    {
        $id = $request->get('project');
        $project = Project::with([
            'projectPlan.citations.source',
            'projectPlan.baselines' => fn ($q) => $q->orderByDesc('version'),
            'milestones' => fn ($q) => $q->orderByRaw('target_date is null')
                ->orderBy('target_date')->orderBy('name'),
            'milestones.citations.source',
            'milestones.workItems:id,name',
            'roles' => fn ($q) => $q->orderBy('name'),
            'roles.citations.source',
            'roles.users:id,name,email',
            'roles.agents:id,name,kind',
            'workItems' => fn ($q) => $q->orderBy('kind')->orderBy('name'),
            'workItems.requirements:id,doc,type,text',
            'workItems.milestones:id,name',
            'workItems.responsibleRole:id,name',
            'workItems.dependencies:id,name',
            'workItems.raciRoles:id,name',
            'workItems.deliveryLinks.checkRuns',
            'workItems.citations.source',
            'risks' => fn ($q) => $q->with('ownerRole:id,name')
                ->orderBy('status')
                ->orderBy('title'),
            'releases' => fn ($q) => $q->withCount(['workItems', 'deployments'])
                ->orderByDesc('released_at')
                ->orderByDesc('created_at'),
            'deployments' => fn ($q) => $q->with('release:id,version')
                ->withCount('deliveryLinks')
                ->orderByDesc('deployed_at')
                ->orderByDesc('created_at'),
        ])->find($id);

        if (! $project) {
            return Response::error("Project [{$id}] not found.");
        }

        $plan = $project->projectPlan;
        $md = "# Project Management Plan — {$project->name}\n\n";
        $md .= "_Rigor level {$project->rigor_level}_\n\n";

        $md .= "## 1. Project context\n\n";
        if ($project->description) {
            $md .= "{$project->description}\n\n";
        }
        $md .= "- **Project id:** `{$project->id}`\n";
        $md .= '- **Plan status:** '.($plan?->status ?? '_(no PMP yet)_')."\n\n";

        $md .= "## 2. Project plan\n\n";
        if (! $plan) {
            $md .= "_No ProjectPlan has been drafted. Call `upsert-project-plan` to create one._\n\n";
        } else {
            foreach (
                [
                    '2.1 Scope' => $plan->scope_summary,
                    '2.2 Objectives' => $plan->objectives,
                    '2.3 Deliverables' => $plan->deliverables_summary,
                    '2.4 Approach' => $plan->approach,
                    '2.5 Assumptions' => $plan->assumptions,
                    '2.6 Constraints' => $plan->constraints,
                    '2.7 Budget' => $plan->budget_summary,
                ] as $heading => $body
            ) {
                $md .= "### {$heading}\n\n";
                $md .= $body ? "{$body}\n\n" : "_None recorded._\n\n";
            }
            $md .= CitationRenderer::render($plan->citations, indent: '');
            if ($plan->citations->isNotEmpty()) {
                $md .= "\n";
            }
            $md .= "### 2.8 Baselines\n\n";
            if ($plan->baselines->isEmpty()) {
                $md .= "_No baselines recorded._\n\n";
            } else {
                foreach ($plan->baselines as $baseline) {
                    $md .= "- **v{$baseline->version}** — {$baseline->baselined_at->toDateTimeString()}";
                    if ($baseline->note) {
                        $md .= " — {$baseline->note}";
                    }
                    $md .= "\n";
                }
                $md .= "\n";
            }
        }

        $md .= "## 3. Project organization\n\n";
        if ($plan?->organization_summary) {
            $md .= "{$plan->organization_summary}\n\n";
        }
        if ($project->roles->isEmpty()) {
            $md .= "_No roles defined._\n\n";
        } else {
            $md .= "### 3.1 Roles\n\n";
            foreach ($project->roles as $r) {
                $md .= "- **{$r->name}**";
                if ($r->responsibilities) {
                    $md .= " — {$r->responsibilities}";
                }
                $md .= "\n";
                $fillers = collect()
                    ->merge($r->users->map(fn ($u) => "{$u->name} (user)"))
                    ->merge($r->agents->map(fn ($a) => "{$a->name} (agent".($a->kind ? ", {$a->kind}" : '').')'));
                if ($fillers->isNotEmpty()) {
                    $md .= '  - _Filled by:_ '.$fillers->implode(', ')."\n";
                }
                $md .= CitationRenderer::render($r->citations);
            }
            $md .= "\n";
        }

        $md .= "## 4. Schedule (milestones)\n\n";
        if ($project->milestones->isEmpty()) {
            $md .= "_No milestones defined._\n\n";
        } else {
            $md .= "| Milestone | Target | Status | Work items |\n";
            $md .= "| --- | --- | --- | --- |\n";
            foreach ($project->milestones as $m) {
                $date = $m->target_date?->toDateString() ?? '—';
                $count = $m->workItems->count();
                $md .= "| **{$m->name}** | {$date} | {$m->status} | {$count} |\n";
            }
            $md .= "\n";
            foreach ($project->milestones as $m) {
                if ($m->exit_criteria || $m->citations->isNotEmpty()) {
                    $md .= "- **{$m->name}**";
                    if ($m->exit_criteria) {
                        $md .= "\n  - _Exit criteria:_ {$m->exit_criteria}";
                    }
                    $md .= "\n";
                    $md .= CitationRenderer::render($m->citations);
                }
            }
        }

        $md .= "## 5. Work breakdown\n\n";
        $roots = $project->workItems->whereNull('parent_id')->values();
        if ($roots->isEmpty() && $project->workItems->isEmpty()) {
            $md .= "_No work items defined._\n\n";
        } else {
            // Build an in-memory parent → children map so we don't N+1 on deep trees.
            $byParent = $project->workItems->groupBy('parent_id');
            $renderChildren = function ($parentId, int $depth) use (&$renderChildren, $byParent) {
                $kids = $byParent[$parentId] ?? collect();
                $out = '';
                foreach ($kids->sortBy('name') as $w) {
                    $indent = str_repeat('  ', $depth);
                    $out .= "{$indent}- **[{$w->kind}]** {$w->name}";
                    $bits = [];
                    if ($w->status !== 'todo') {
                        $bits[] = $w->status;
                    }
                    if ($w->responsibleRole) {
                        $bits[] = 'owner: '.$w->responsibleRole->name;
                    }
                    if ($w->planned_start_date) {
                        $bits[] = 'start '.$w->planned_start_date->toDateString();
                    }
                    if ($w->due_date) {
                        $bits[] = 'due '.$w->due_date->toDateString();
                    }
                    if ($bits !== []) {
                        $out .= ' _('.implode(', ', $bits).')_';
                    }
                    $out .= "\n";
                    if ($w->requirements->isNotEmpty()) {
                        $reqs = $w->requirements->map(fn ($r) => "{$r->doc}/{$r->type}#{$r->id}")->implode(', ');
                        $out .= "{$indent}  - _Covers:_ {$reqs}\n";
                    }
                    if ($w->milestones->isNotEmpty()) {
                        $mss = $w->milestones->pluck('name')->implode(', ');
                        $out .= "{$indent}  - _Delivers to:_ {$mss}\n";
                    }
                    if ($w->dependencies->isNotEmpty()) {
                        $deps = $w->dependencies->pluck('name')->implode(', ');
                        $out .= "{$indent}  - _Depends on:_ {$deps}\n";
                    }
                    if ($w->raciRoles->isNotEmpty()) {
                        $labels = [
                            'r' => 'R',
                            'a' => 'A',
                            'c' => 'C',
                            'i' => 'I',
                        ];
                        $assignments = collect($labels)
                            ->map(function (string $label, string $raci) use ($w) {
                                $roles = $w->raciRoles
                                    ->filter(fn ($role) => $role->pivot->raci === $raci)
                                    ->pluck('name');

                                return $roles->isEmpty()
                                    ? null
                                    : "{$label}: ".$roles->implode(', ');
                            })
                            ->filter()
                            ->values()
                            ->implode('; ');
                        $out .= "{$indent}  - _RACI:_ {$assignments}\n";
                    }
                    if ($w->deliveryLinks->isNotEmpty()) {
                        $links = $w->deliveryLinks
                            ->map(fn ($link) => "{$link->type}: {$link->ref}")
                            ->implode(', ');
                        $out .= "{$indent}  - _Delivery evidence:_ {$links}\n";
                        $checks = $w->deliveryLinks
                            ->flatMap->checkRuns
                            ->map(fn ($check) => "{$check->name}: {$check->status}".($check->conclusion ? "/{$check->conclusion}" : ''))
                            ->implode(', ');
                        if ($checks !== '') {
                            $out .= "{$indent}  - _Check evidence:_ {$checks}\n";
                        }
                    }
                    $out .= CitationRenderer::render($w->citations, indent: $indent.'  ');
                    $out .= $renderChildren($w->id, $depth + 1);
                }

                return $out;
            };
            $md .= $renderChildren(null, 0);
            $md .= "\n";
        }

        $md .= "## 6. Risk register\n\n";
        if ($project->risks->isEmpty()) {
            $md .= "_No risks recorded._\n\n";
        } else {
            foreach ($project->risks->groupBy('status') as $status => $risks) {
                $md .= "### {$status}\n\n";
                $md .= "| Risk | Category | Exposure | Owner role | Mitigation |\n";
                $md .= "| --- | --- | --- | --- | --- |\n";
                foreach ($risks as $risk) {
                    $owner = $risk->ownerRole?->name ?? '—';
                    $mitigation = $risk->mitigation_plan ? str_replace(["\r", "\n"], ' ', $risk->mitigation_plan) : '—';
                    $md .= "| **{$risk->title}** | {$risk->category} | {$risk->probability} × {$risk->impact} | {$owner} | {$mitigation} |\n";
                }
                $md .= "\n";
            }
        }

        $md .= "## 7. Release and deployment records\n\n";
        if ($project->releases->isEmpty()) {
            $md .= "_No releases recorded._\n\n";
        } else {
            $md .= "| Release | Status | Released | Work items | Deployments |\n";
            $md .= "| --- | --- | --- | --- | --- |\n";
            foreach ($project->releases as $release) {
                $releasedAt = $release->released_at?->toDateString() ?? '—';
                $md .= "| **{$release->version}** | {$release->status} | {$releasedAt} | {$release->work_items_count} | {$release->deployments_count} |\n";
            }
            $md .= "\n";
        }

        if ($project->deployments->isEmpty()) {
            $md .= "_No deployments recorded._\n\n";
        } else {
            $md .= "| Environment | Release | Status | Deployed | Delivery links |\n";
            $md .= "| --- | --- | --- | --- | --- |\n";
            foreach ($project->deployments as $deployment) {
                $release = $deployment->release?->version ?? '—';
                $deployedAt = $deployment->deployed_at?->toDateString() ?? '—';
                $md .= "| {$deployment->environment} | {$release} | {$deployment->status} | {$deployedAt} | {$deployment->delivery_links_count} |\n";
            }
            $md .= "\n";
        }

        return Response::text($md);
    }
}
