<?php

namespace App\Mcp\Resources;

use App\Models\ThemeAssignment;
use App\Models\WorkItem;
use Illuminate\Support\Collection;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\MimeType;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Contracts\HasUriTemplate;
use Laravel\Mcp\Server\Resource;
use Laravel\Mcp\Support\UriTemplate;

#[Name('Work Item Implementation Brief')]
#[Description('Context bundle for implementing a work item: requirements, architecture, dependencies, RACI, mockups, and delivery evidence.')]
#[MimeType('text/markdown')]
class WorkItemImplementationBriefResource extends Resource implements HasUriTemplate
{
    public function uriTemplate(): UriTemplate
    {
        return new UriTemplate('growth://work-items/{work_item}/implementation-brief');
    }

    public function handle(Request $request): Response
    {
        $id = $request->get('work_item');
        $workItem = WorkItem::with([
            'project.designViews.concerns',
            'project.designViews.elements' => fn ($query) => $query->orderBy('kind')->orderBy('name'),
            'project.themes' => fn ($query) => $query->orderByDesc('is_default')->orderBy('name'),
            'project.themeAssignments.theme' => fn ($query) => $query->orderBy('name'),
            'requirements',
            'dependencies',
            'dependents',
            'milestones',
            'raciRoles',
            'responsibleRole',
            'mockups',
            'deliveryLinks.checkRuns',
        ])->find($id);

        if (! $workItem) {
            return Response::error("Work item [{$id}] not found.");
        }

        $project = $workItem->project;
        $md = "# Implementation Brief - {$workItem->reference()} {$workItem->name}\n\n";
        $md .= "Use this brief before implementing or completing the work item. It bundles the context most likely to affect the artifact you produce.\n\n";

        $md .= "## Work Item\n\n";
        $md .= "- **Project:** {$project->name}\n";
        $md .= "- **Kind:** {$workItem->kind}\n";
        $md .= "- **Status:** {$workItem->status}\n";
        if ($workItem->description) {
            $md .= "- **Description:** {$workItem->description}\n";
        }
        if ($workItem->responsibleRole) {
            $md .= "- **Responsible role:** {$workItem->responsibleRole->name}\n";
        }
        $md .= "\n";

        $md .= "## Requirements To Preserve\n\n";
        if ($workItem->requirements->isEmpty()) {
            $md .= "_No requirements are linked to this work item yet._\n\n";
        } else {
            foreach ($workItem->requirements->sortBy(['doc', 'number']) as $requirement) {
                $md .= "- **{$requirement->reference()}** ({$requirement->type}, {$requirement->priority}): {$requirement->text}\n";
                if (! empty($requirement->acceptance_criteria)) {
                    foreach ($requirement->acceptance_criteria as $check) {
                        $md .= "  - Acceptance: {$check}\n";
                    }
                }
            }
            $md .= "\n";
        }

        $md .= "## Architecture Context\n\n";
        $views = $project->designViews->sortBy(['viewpoint', 'name'])->values();
        if ($views->isEmpty()) {
            $md .= "_No architecture views are captured for this project yet._\n\n";
        } else {
            foreach ($views as $view) {
                $md .= "### {$view->name}\n\n";
                $md .= "- **Viewpoint:** `{$view->viewpoint}`\n";
                if ($view->description) {
                    $md .= "- **Description:** {$view->description}\n";
                }
                if ($view->concerns->isNotEmpty()) {
                    $md .= '- **Concerns:** '.implode('; ', $view->concerns->pluck('text')->all())."\n";
                }
                if ($view->elements->isNotEmpty()) {
                    $md .= "- **Elements:**\n";
                    foreach ($view->elements as $element) {
                        $md .= "  - [{$element->kind}] {$element->name}";
                        if ($element->type) {
                            $md .= " ({$element->type})";
                        }
                        if ($element->purpose) {
                            $md .= " - {$element->purpose}";
                        }
                        $md .= "\n";
                    }
                }
                $md .= "\n";
            }
        }

        $md .= "## Dependencies And Milestones\n\n";
        $md .= $workItem->dependencies->isEmpty()
            ? "- **Dependencies:** none recorded.\n"
            : '- **Dependencies:** '.$workItem->dependencies->map(fn (WorkItem $item): string => "{$item->reference()} {$item->name}")->implode('; ')."\n";
        $md .= $workItem->dependents->isEmpty()
            ? "- **Dependents:** none recorded.\n"
            : '- **Dependents:** '.$workItem->dependents->map(fn (WorkItem $item): string => "{$item->reference()} {$item->name}")->implode('; ')."\n";
        $md .= $workItem->milestones->isEmpty()
            ? "- **Milestones:** none linked.\n\n"
            : '- **Milestones:** '.$workItem->milestones->pluck('name')->implode('; ')."\n\n";

        $md .= "## RACI And Mockups\n\n";
        if ($workItem->raciRoles->isEmpty()) {
            $md .= "- **RACI:** none assigned.\n";
        } else {
            foreach ($workItem->raciRoles as $role) {
                $md .= '- **'.strtoupper((string) $role->pivot->raci).":** {$role->name}\n";
            }
        }
        if ($workItem->mockups->isEmpty()) {
            $md .= "- **Mockups:** none captured.\n\n";
        } else {
            foreach ($workItem->mockups as $mockup) {
                $md .= "- **Mockup:** {$mockup->name} (`growth://mockups/{$mockup->id}`)\n";
            }
            $md .= "\n";
        }

        $md .= "## Themes\n\n";
        if ($project->themes->isEmpty()) {
            $md .= "_No themes are captured yet._\n\n";
        } else {
            $default = $project->themes->firstWhere('is_default', true);
            if ($default) {
                $md .= "- **Default theme:** {$default->name} (`{$default->slug}`)\n";
            }
            $md .= "- Use the default theme unless the implementation request names another theme slug.\n";
            foreach ($project->themes as $theme) {
                $md .= "- **{$theme->name}** (`{$theme->slug}`)";
                if ($theme->is_default) {
                    $md .= ' - default';
                }
                if ($theme->description) {
                    $md .= " - {$theme->description}";
                }
                $md .= "\n";
            }
            $md .= "\n";

            $md .= "### Scoped Theme Assignments\n\n";
            if ($project->themeAssignments->isEmpty()) {
                $md .= "_No scoped theme assignments are captured yet._\n\n";
            } else {
                $relevant = $this->relevantThemeAssignments($project->themeAssignments, $workItem);
                if ($relevant->isNotEmpty()) {
                    $md .= "Most relevant to this work item:\n";
                    foreach ($relevant as $assignment) {
                        $md .= "- {$assignment->scopeLabel()} uses `{$assignment->theme->slug}`";
                        if ($assignment->notes) {
                            $md .= " - {$assignment->notes}";
                        }
                        $md .= "\n";
                    }
                    $md .= "\n";
                }

                $md .= "All captured assignments:\n";
                foreach ($project->themeAssignments->sortBy(['scope_type', 'scope_key']) as $assignment) {
                    $md .= "- {$assignment->scopeLabel()} uses `{$assignment->theme->slug}`";
                    if ($assignment->notes) {
                        $md .= " - {$assignment->notes}";
                    }
                    $md .= "\n";
                }
                $md .= "\n";
            }
        }

        $md .= "## Delivery Evidence\n\n";
        if ($workItem->deliveryLinks->isEmpty()) {
            $md .= "_No delivery evidence is linked yet._\n\n";
        } else {
            foreach ($workItem->deliveryLinks as $link) {
                $md .= "- **{$link->type}:** {$link->ref}";
                if ($link->url) {
                    $md .= " ({$link->url})";
                }
                $md .= "\n";
                foreach ($link->checkRuns as $check) {
                    $md .= "  - Check {$check->name}: {$check->status}";
                    if ($check->conclusion) {
                        $md .= " / {$check->conclusion}";
                    }
                    $md .= "\n";
                }
            }
            $md .= "\n";
        }

        $md .= "## Implementation Guidance\n\n";
        $md .= "- Preserve linked requirement behavior and acceptance checks.\n";
        $md .= "- Respect architecture context where it affects component boundaries, data flow, or user-facing behavior.\n";
        $md .= "- Apply theme guidance when changing user-facing UI; browser-local preview selection is not server state, so use the project default or an explicitly requested theme slug.\n";
        $md .= "- Consider dependencies, RACI, mockups, and existing delivery evidence before changing code.\n";

        return Response::text($md);
    }

    /**
     * @param  Collection<int,ThemeAssignment>  $assignments
     * @return Collection<int,ThemeAssignment>
     */
    private function relevantThemeAssignments(Collection $assignments, WorkItem $workItem): Collection
    {
        $keys = [
            ['work_item', $workItem->id],
            ['work_item', $workItem->reference()],
        ];

        foreach ($workItem->mockups as $mockup) {
            array_unshift($keys, ['mockup', $mockup->id]);
        }

        return $assignments
            ->filter(fn (ThemeAssignment $assignment): bool => in_array([$assignment->scope_type, $assignment->scope_key], $keys, true))
            ->values();
    }
}
