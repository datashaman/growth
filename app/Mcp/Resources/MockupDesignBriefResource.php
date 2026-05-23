<?php

namespace App\Mcp\Resources;

use App\Models\Requirement;
use App\Models\SpecMockup;
use App\Models\ThemeAssignment;
use App\Models\WorkItem;
use App\Support\ThemePreviewSpecimen;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\MimeType;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Contracts\HasUriTemplate;
use Laravel\Mcp\Server\Resource;
use Laravel\Mcp\Support\UriTemplate;

#[Name('Mockup Design Brief')]
#[Description('Context bundle for generating or refining a mockup: owner details, linked requirements, existing mockups, and architecture views/elements.')]
#[MimeType('text/markdown')]
class MockupDesignBriefResource extends Resource implements HasUriTemplate
{
    /** @var array<string, class-string<Model>> */
    private const OWNER_MODELS = [
        'work_item' => WorkItem::class,
        'requirement' => Requirement::class,
    ];

    public function uriTemplate(): UriTemplate
    {
        return new UriTemplate('growth://owners/{owner_type}/{owner_id}/mockup-design-brief');
    }

    public function handle(Request $request): Response
    {
        $ownerType = $request->get('owner_type');
        $ownerId = $request->get('owner_id');
        $model = self::OWNER_MODELS[$ownerType] ?? null;

        if ($model === null) {
            return Response::error("Unsupported mockup owner type [{$ownerType}].");
        }

        $owner = $model::find($ownerId);
        if (! $owner) {
            return Response::error("Mockup owner {$ownerType} [{$ownerId}] not found.");
        }

        $project = $owner->project()
            ->with([
                'designViews.concerns',
                'designViews.elements' => fn ($query) => $query->orderBy('kind')->orderBy('name'),
                'themes' => fn ($query) => $query->orderByDesc('is_default')->orderBy('name'),
                'themeAssignments.theme' => fn ($query) => $query->orderBy('name'),
            ])
            ->firstOrFail();

        $requirements = $owner instanceof WorkItem
            ? $owner->requirements()->orderBy('doc')->orderBy('number')->get()
            : collect([$owner]);

        $mockups = SpecMockup::query()
            ->where('owner_type', $ownerType)
            ->where('owner_id', $ownerId)
            ->orderBy('name')
            ->get();

        $md = "# Mockup Design Brief - {$project->name}\n\n";
        $md .= "Use this brief before generating or refining the mockup. It bundles the most relevant context so the artifact reflects captured project intent and architecture.\n\n";

        $md .= "## Owner\n\n";
        if ($owner instanceof WorkItem) {
            $md .= "- **Type:** work item\n";
            $md .= "- **Reference:** {$owner->reference()}\n";
            $md .= "- **Name:** {$owner->name}\n";
            $md .= "- **Kind:** {$owner->kind}\n";
            $md .= "- **Status:** {$owner->status}\n";
            if ($owner->description) {
                $md .= "- **Description:** {$owner->description}\n";
            }
        } else {
            $md .= "- **Type:** requirement\n";
            $md .= "- **Reference:** {$owner->reference()}\n";
            $md .= "- **Layer:** {$owner->doc}\n";
            $md .= "- **Requirement type:** {$owner->type}\n";
            $md .= "- **Text:** {$owner->text}\n";
            if ($owner->rationale) {
                $md .= "- **Rationale:** {$owner->rationale}\n";
            }
        }
        $md .= "\n";

        $md .= "## Requirements To Preserve\n\n";
        if ($requirements->isEmpty()) {
            $md .= "_No requirements are linked to this work item yet._\n\n";
        } else {
            foreach ($requirements as $requirement) {
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
                    $md .= "- **Concerns addressed:**\n";
                    foreach ($view->concerns as $concern) {
                        $md .= "  - {$concern->text}\n";
                    }
                }
                if ($view->elements->isNotEmpty()) {
                    $md .= "- **Elements to consider in the mockup:**\n";
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

        $md .= "## Existing Mockups\n\n";
        if ($mockups->isEmpty()) {
            $md .= "_No mockups exist for this owner yet._\n\n";
        } else {
            foreach ($mockups as $mockup) {
                $md .= "- {$mockup->name} (`growth://mockups/{$mockup->id}`)\n";
            }
            $md .= "\n";
        }

        $md .= "## Themes\n\n";
        if ($project->themes->isEmpty()) {
            $md .= "_No themes exist yet. Generate mockups as self-contained HTML and avoid inventing remote stylesheet dependencies._\n\n";
        } else {
            $default = $project->themes->firstWhere('is_default', true);
            if ($default) {
                $md .= "- **Default theme:** {$default->name} (`{$default->slug}`)\n";
            }
            $md .= "- Apply reusable visual language through themes rather than embedding a whole theme picker inside the mockup artifact.\n";
            $md .= "- Keep mockup HTML self-contained; theme CSS is overlaid by Growth during preview.\n\n";

            foreach ($project->themes as $theme) {
                $tokenNames = implode(', ', array_keys($theme->normalizedCssTokens()));
                $md .= "### {$theme->name}";
                if ($theme->is_default) {
                    $md .= ' (default)';
                }
                $md .= "\n\n";
                $md .= "- **Slug:** `{$theme->slug}`\n";
                if ($theme->description) {
                    $md .= "- **Description:** {$theme->description}\n";
                }
                if ($theme->design_notes) {
                    $md .= "- **Design notes:** {$theme->design_notes}\n";
                }
                $md .= $tokenNames !== ''
                    ? "- **CSS tokens:** {$tokenNames}\n"
                    : "- **CSS tokens:** none captured\n";
                $md .= '- **Raw CSS:** '.(filled($theme->raw_css) ? 'present' : 'none')."\n\n";
            }

            $md .= "### Mockup CSS Boundary\n\n";
            $md .= "- Treat assigned/default Growth themes as the reusable visual design-system layer.\n";
            $md .= "- Keep local mockup CSS focused on semantic structure, one-off layout, and state-specific affordances.\n";
            $md .= "- Do not duplicate broad component styling for cards, panels, buttons, tables, grids, badges, or theme-like color tokens across mockups; put reusable visual differentiation in theme `raw_css`, CSS tokens, and design notes instead.\n\n";

            $md .= ThemePreviewSpecimen::contractMarkdown();

            $md .= "### Scoped Theme Assignments\n\n";
            if ($project->themeAssignments->isEmpty()) {
                $md .= "_No scoped theme assignments are captured yet. Use the default theme unless the owner context says otherwise._\n\n";
            } else {
                $relevant = $this->relevantThemeAssignments($project->themeAssignments, $owner);
                if ($relevant->isNotEmpty()) {
                    $md .= "Most relevant to this owner:\n";
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

        $md .= "## Expected Screen Coverage\n\n";
        $coverage = $this->expectedScreenCoverage($owner, $requirements);
        $md .= $coverage['intro']."\n";
        foreach ($coverage['items'] as $item) {
            $md .= "- {$item}\n";
        }
        $md .= "\n";

        $md .= "## Generation Guidance\n\n";
        $md .= "- Represent relevant architecture views/elements in the mockup when they affect layout, states, flows, or component boundaries.\n";
        $md .= "- Preserve linked requirement behavior and acceptance checks.\n";
        $md .= "- Do not render Growth artifact metadata such as work item or requirement references (`WI-001`, `SRS-001`) inside the mockup UI; use the human-facing screen title and domain labels instead.\n";
        $md .= "- Use separate named mockups for materially different screens or states, such as empty/loading states, validation failures, stale analytics, fulfillment confirmation, or mediation/exception handling.\n";
        $md .= "- Keep local JavaScript inside one mockup for natural interactions that do not replace the whole screen, such as filtering, toggles, inline validation, submit feedback, and confirmation dialogs.\n";
        $md .= "- After creating or refining a mockup, read its preview resource (`growth://mockups/{mockup}` or `growth://mockups/{mockup}/{revision}`) to check browser-visible text and metadata warnings; read `/screenshot` only when visual pixels are needed.\n";
        $md .= "- If the mockup intentionally diverges from architecture context, make the mismatch visible in the artifact or its notes.\n";

        return Response::text($md);
    }

    /**
     * @param  Collection<int,ThemeAssignment>  $assignments
     * @return Collection<int,ThemeAssignment>
     */
    private function relevantThemeAssignments(Collection $assignments, Model $owner): Collection
    {
        $keys = $this->ownerThemeAssignmentKeys($owner);

        return $assignments
            ->filter(fn (ThemeAssignment $assignment): bool => in_array([$assignment->scope_type, $assignment->scope_key], $keys, true))
            ->values();
    }

    /**
     * @return array<int,array{0:string,1:string}>
     */
    private function ownerThemeAssignmentKeys(Model $owner): array
    {
        if ($owner instanceof WorkItem) {
            $keys = [
                ['work_item', $owner->id],
                ['work_item', $owner->reference()],
            ];

            foreach ($owner->mockups as $mockup) {
                array_unshift($keys, ['mockup', $mockup->id]);
            }

            return $keys;
        }

        if ($owner instanceof Requirement) {
            $keys = [
                ['requirement', $owner->id],
                ['requirement', $owner->reference()],
            ];

            foreach ($owner->mockups as $mockup) {
                array_unshift($keys, ['mockup', $mockup->id]);
            }

            return $keys;
        }

        return [];
    }

    /**
     * @param  Collection<int, Requirement>  $requirements
     * @return array{intro:string,items:list<string>}
     */
    private function expectedScreenCoverage(Model $owner, $requirements): array
    {
        $items = [];

        if ($owner instanceof WorkItem && $owner->needs_mockups) {
            $items[] = "Create a primary named mockup for `{$owner->name}`.";
        }

        foreach ($requirements as $requirement) {
            if ($requirement->renders_ui) {
                $items[] = "Cover UI requirement {$requirement->reference()} with at least one named mockup.";
            }

            $text = strtolower(implode(' ', array_filter([
                $requirement->text,
                $requirement->rationale,
                ...($requirement->acceptance_criteria ?? []),
            ])));

            foreach ($this->stateSuggestions($text) as $suggestion) {
                $items[] = "{$suggestion} (derived from {$requirement->reference()}).";
            }
        }

        $items = array_values(array_unique($items));

        if ($items === []) {
            return [
                'intro' => 'No distinct extra screens are obvious from the captured owner context. Start with one clear default mockup, then add named alternatives only when the brief implies materially different UI states.',
                'items' => [
                    'Use `name` values such as `empty state`, `validation failure`, or `confirmation` when those distinct screens become relevant.',
                ],
            ];
        }

        return [
            'intro' => 'Suggested named mockups or required state coverage, derived from this owner and its linked requirements:',
            'items' => $items,
        ];
    }

    /**
     * @return list<string>
     */
    private function stateSuggestions(string $text): array
    {
        $suggestions = [];

        foreach ([
            ['terms' => ['empty', 'no data', 'no results'], 'copy' => 'Add an `empty state` named mockup'],
            ['terms' => ['invalid', 'validation', 'error', 'reject', 'failure'], 'copy' => 'Add a `validation failure` named mockup'],
            ['terms' => ['stale', 'outdated', 'expired'], 'copy' => 'Add a `stale data` named mockup'],
            ['terms' => ['confirm', 'confirmation', 'success', 'submitted', 'fulfilled', 'complete'], 'copy' => 'Add a `confirmation` named mockup'],
            ['terms' => ['exception', 'conflict', 'mediate', 'mediation', 'manual review'], 'copy' => 'Add an `exception handling` named mockup'],
            ['terms' => ['loading', 'pending', 'processing'], 'copy' => 'Add a `loading or pending` named mockup'],
        ] as $rule) {
            foreach ($rule['terms'] as $term) {
                if (str_contains($text, $term)) {
                    $suggestions[] = $rule['copy'];
                    break;
                }
            }
        }

        return $suggestions;
    }
}
